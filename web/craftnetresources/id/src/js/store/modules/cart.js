import Vue from 'vue'
import Vuex from 'vuex'
import api from '../../api/cart'
import CartHelper from '../../helpers/cart'

Vue.use(Vuex)

/**
 * State
 */
const state = {
    cart: null,
    selectedExpiryDates: {},
}

/**
 * Getters
 */
const getters = {
    cartTotal() {
        return 0
    },

    cartTotalItems(state) {
        if(state.cart && state.cart.lineItems) {
            return state.cart.lineItems.length
        }

        return 0
    },

    cartItems(state, getters, rootState) {
        let cartItems = []

        if (state.cart) {
            const lineItems = state.cart.lineItems

            lineItems.forEach(lineItem => {
                let cartItem = {}

                cartItem.id = lineItem.id
                cartItem.lineItem = lineItem

                if (lineItem.purchasable.type === 'plugin-edition') {
                    cartItem.plugin = rootState.pluginStore.plugins.find(p => p.handle === lineItem.purchasable.plugin.handle)
                }

                cartItems.push(cartItem)
            })
        }

        return cartItems
    },

    cartItemsData(state) {
        return CartHelper.getCartItemsData(state.cart)
    }
}

/**
 * Actions
 */
const actions = {
    getCart({dispatch, commit, rootState, state}) {
        return new Promise((resolve, reject) => {
            if (!state.cart) {
                dispatch('getOrderNumber')
                    .then(orderNumber => {
                        if (orderNumber) {
                            api.getCart(orderNumber)
                                .then((response) => {
                                    if (!response.error) {
                                        commit('updateCart', {response: response.data})

                                        dispatch('requestMissingPlugins')
                                            .then(() => {
                                                resolve()
                                            })
                                            .catch(() => {
                                                reject('Couldn’t get missing plugins')
                                            })
                                    } else {
                                        // Couldn’t get cart for this order number? Try to create a new one.
                                        const data = {}

                                        if (rootState.account.user) {
                                            data.email = rootState.account.user.email
                                        }

                                        api.createCart(data)
                                            .then((createCartResponse) => {
                                                commit('updateCart', {response: createCartResponse.data})
                                                dispatch('saveOrderNumber', {orderNumber: createCartResponse.data.cart.number})
                                                resolve()
                                            })
                                            .catch((createCartError) => {
                                                if (createCartError.response.data.error) {
                                                    reject(createCartError.response.data.error)
                                                    return;
                                                }

                                                if (createCartError.message) {
                                                    reject(createCartError.message)
                                                    return;
                                                }

                                                reject('Couldn’t create cart')
                                            })
                                    }
                                })
                                .catch((error) => {
                                    if (error.response.data.message && error.response.data.message !== 'Cart Already Completed' && error.response.data.message !== 'Cart Not Found') {
                                        reject(error.response.data.message)
                                        return;
                                    }

                                    if (error.response.data.error) {
                                        reject(error.response.data.error)
                                        return;
                                    }

                                    const data = {}

                                    if (rootState.account.user) {
                                        data.email = rootState.account.user.email
                                    }

                                    api.createCart(data)
                                        .then((createCartResponse) => {
                                            commit('updateCart', {response: createCartResponse.data})
                                            dispatch('saveOrderNumber', {orderNumber: createCartResponse.data.cart.number})
                                            resolve()
                                        })
                                        .catch((createCartError) => {
                                            if (createCartError.response.data.error) {
                                                reject(createCartError.response.data.error)
                                                return;
                                            }

                                            if (createCartError.message) {
                                                reject(createCartError.message)
                                                return;
                                            }

                                            reject('Couldn’t create cart')
                                        })
                                })
                        } else {
                            // No order number yet? Create a new cart.
                            const data = {}

                            if (rootState.account.user) {
                                data.email = rootState.account.user.email
                            }

                            api.createCart(data)
                                .then((createCartResponse) => {
                                    commit('updateCart', {response: createCartResponse.data})
                                    dispatch('saveOrderNumber', {orderNumber: createCartResponse.data.cart.number})
                                    resolve()
                                })
                                .catch((createCartError) => {
                                    if (createCartError.response.data.error) {
                                        reject(createCartError.response.data.error)
                                        return;
                                    }

                                    if (createCartError.message) {
                                        reject(createCartError.message)
                                        return;
                                    }

                                    reject('Couldn’t create cart.')
                                })
                        }
                    })
            } else {
                resolve()
            }
        })
    },

    requestMissingPlugins({state, dispatch}) {
        return new Promise((resolve, reject) => {

            // request plugins we don’t have yet
            const pluginIds = []

            state.cart.lineItems.forEach(lineItem => {
                if (lineItem.purchasable.plugin) {
                    if (pluginIds.indexOf(lineItem.purchasable.plugin.id) < 0) {
                        pluginIds.push(lineItem.purchasable.plugin.id)
                    }
                }
            })

            if (pluginIds.length > 0) {
                dispatch('pluginStore/getPlugins', pluginIds, {root: true})
                    .then(() => {
                        resolve()
                    })
                    .catch(() => {
                        reject('Couldn’t get cart')
                    })
            } else {
                resolve()
            }
        })
    },

    saveCart({commit, state}, data) {
        return new Promise((resolve, reject) => {
            const cart = state.cart

            api.updateCart(cart.number, data)
                .then((response) => {
                    if (!response.data.errors) {
                        commit('updateCart', {response: response.data})
                        resolve(response)
                    } else {
                        reject(response)
                    }
                })
                .catch((response) => {
                    reject(response)
                })
        })
    },

    resetCart({commit, dispatch}) {
        return new Promise((resolve, reject) => {
            commit('resetCart')
            dispatch('resetOrderNumber')
            dispatch('getCart')
                .then(response => {
                    resolve(response)
                })
                .catch(response => {
                    reject(response)
                })
        })
    },

    resetOrderNumber() {
        api.resetOrderNumber()
    },

    // eslint-disable-next-line
    checkout({}, data) {
        return new Promise((resolve, reject) => {
            api.checkout(data)
                .then(response => {
                    resolve(response)
                })
                .catch(response => {
                    reject(response)
                })
        })
    },

    getOrderNumber({state}) {
        return new Promise((resolve, reject) => {
            if (state.cart && state.cart.number) {
                const orderNumber = state.cart.number
                resolve(orderNumber)
            } else {
                api.getOrderNumber(orderNumber => {
                    resolve(orderNumber)
                }, response => {
                    reject(response)
                })
            }
        })
    },

    // eslint-disable-next-line
    saveOrderNumber({}, {orderNumber}) {
        api.saveOrderNumber(orderNumber)
    },

    addToCart({commit, state, dispatch}, newItems) {
        return new Promise((resolve, reject) => {
            dispatch('getCart')
                .then(() => {
                    const cart = state.cart
                    let items = CartHelper.getCartItemsData(cart)

                    newItems.forEach(newItem => {
                        let item = {...newItem}

                        if (!item.expiryDate) {
                            item.expiryDate = '1y'
                        }

                        items.forEach((cartItem, cartItemKey) => {
                            if (cartItem.licenseKey === item.licenseKey) {
                                items.splice(cartItemKey, 1)
                            }
                        })

                        items.push(item)
                    })

                    let data = {
                        items,
                    }

                    api.updateCart(cart.number, data)
                        .then((updateCartResponse) => {
                            commit('updateCart', {response: updateCartResponse.data})

                            dispatch('requestMissingPlugins')
                                .then(() => {
                                    resolve(updateCartResponse)
                                })
                                .catch(() => {
                                    reject('Couldn’t get missing plugins')
                                })
                        })
                        .catch((error) => {
                            if (error.response.data.error) {
                                reject(error.response.data.error)
                                return;
                            }

                            if (error.message) {
                                reject(error.message)
                                return;
                            }

                            reject('Couldn’t update cart.')
                        })
                })
                .catch(reject)
        })
    },

    removeFromCart({commit, dispatch}, lineItemKey) {
        return new Promise((resolve, reject) => {
            dispatch('getCart')
                .then(() => {
                    const cart = state.cart
                    let items = CartHelper.getCartItemsData(cart)
                    items.splice(lineItemKey, 1)

                    let data = {
                        items,
                    }

                    api.updateCart(cart.number, data)
                        .then((response) => {
                            commit('updateCart', {response: response.data})
                            resolve(response)
                        })
                        .catch((response) => {
                            reject(response)
                        })
                })
                .catch(reject)
        })
    },

    updateItem({commit, state}, {itemKey, item}) {
        return new Promise((resolve, reject) => {
            const cart = state.cart

            let items = CartHelper.getCartItemsData(cart)

            items[itemKey] = item

            let data = {
                items,
            }

            api.updateCart(cart.number, data)
                .then((response) => {
                    commit('updateCart', {response: response.data})
                    resolve(response)
                })
                .catch((response) => {
                    reject(response)
                })
        })
    },
}

/**
 * Mutations
 */
const mutations = {
    updateCart(state, {response}) {
        state.cart = response.cart
        state.stripePublicKey = response.stripePublicKey

        const selectedExpiryDates = {}

        state.cart.lineItems.forEach(lineItem => {
            selectedExpiryDates[lineItem.id] = lineItem.options.expiryDate
        })

        state.selectedExpiryDates = selectedExpiryDates
    },

    resetCart(state) {
        state.cart = null
    },

    updateSelectedExpiryDates(state, selectedExpiryDates) {
        state.selectedExpiryDates = selectedExpiryDates
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}

import Vue from 'vue'
import Vuex from 'vuex'
import account from './modules/account'
import app from './modules/app'
import apps from './modules/apps'
import cart from './modules/cart'
import cmsLicenses from './modules/cms-licenses'
import craftId from './modules/craft-id'
import developerSupport from './modules/developer-support'
import partner from './modules/partner'
import pluginLicenses from './modules/plugin-licenses'
import plugins from './modules/plugins'
import pluginStore from './modules/plugin-store'
import stripe from './modules/stripe'

Vue.use(Vuex)

export default new Vuex.Store({
    strict: true,
    modules: {
        account,
        app,
        apps,
        cart,
        cmsLicenses,
        craftId,
        developerSupport,
        partner,
        pluginLicenses,
        plugins,
        pluginStore,
        stripe,
    }
})

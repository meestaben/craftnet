import axios from 'axios'

export default {
    getCoreData() {
        return axios.get(process.env.VUE_APP_CRAFT_API_ENDPOINT + '/plugin-store/core-data', {withCredentials: false})
    },

    getPlugins(pluginIds) {
        return axios.get(process.env.VUE_APP_CRAFT_API_ENDPOINT + '/plugins', {
            params: {
                ids: pluginIds.join(',')
            },
            withCredentials: false
        })
    }
}
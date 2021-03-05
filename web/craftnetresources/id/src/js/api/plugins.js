/* global Craft */

import axios from 'axios'
import FormDataHelper from '../helpers/form-data';
import qs from 'qs'

export default {
    loadDetails(repositoryUrl) {
        return axios.post(Craft.actionUrl + '/craftnet/plugins/load-details&repository=' + encodeURIComponent(repositoryUrl), {}, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    save({plugin}) {
        let formData = new FormData();

        for (let attribute in plugin) {
            if (plugin[attribute] !== null && plugin[attribute] !== undefined) {
                switch (attribute) {
                    case 'iconId':
                    case 'categoryIds':
                    case 'screenshots':
                    case 'screenshotUrls':
                    case 'screenshotIds':
                        for (let i = 0; i < plugin[attribute].length; i++) {
                            FormDataHelper.append(formData, attribute + '[]', plugin[attribute][i]);
                        }
                        break;

                    case 'editions':
                        for (let i = 0; i < plugin[attribute].length; i++) {
                            const edition = plugin[attribute][i]
                            const editionKey = edition.id ? edition.id : 'new';

                            FormDataHelper.append(formData, 'editions['+editionKey+'][price]', edition.price);
                            FormDataHelper.append(formData, 'editions['+editionKey+'][renewalPrice]', edition.renewalPrice);

                            for (let j = 0; j < edition.features.length; j++) {
                                const feature = edition.features[j]
                                FormDataHelper.append(formData, 'editions['+editionKey+'][features]['+j+'][name]', feature.name)
                                FormDataHelper.append(formData, 'editions['+editionKey+'][features]['+j+'][description]', feature.description)
                            }
                        }
                        break;

                    case 'abandoned':
                        FormDataHelper.append(formData, attribute, plugin.abandoned ? 1 : 0)
                        break

                    default:
                        FormDataHelper.append(formData, attribute, plugin[attribute]);
                }
            }
        }

        return axios.post(Craft.actionUrl + '/craftnet/plugins/save', formData, {
                headers: {
                    'X-CSRF-Token': Craft.csrfTokenValue,
                }
            })
    },

    submit(pluginId) {
        const data = {
            pluginId: pluginId,
        }

        return axios.post(Craft.actionUrl + '/craftnet/plugins/submit', qs.stringify(data), {
                headers: {
                    'X-CSRF-Token': Craft.csrfTokenValue,
                }
            })
    },

    getPlugins() {
        return axios.get(Craft.actionUrl + '/craftnet/id/plugins/get-plugins')
    },
}

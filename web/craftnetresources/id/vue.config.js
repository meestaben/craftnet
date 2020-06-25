const fs = require('fs');
const ManifestPlugin = require('webpack-manifest-plugin')

module.exports = {
    devServer: {
        headers: { "Access-Control-Allow-Origin": "*" },
        https: {
            key: process.env.DEV_SSL_KEY ? fs.readFileSync(process.env.DEV_SSL_KEY) : null,
            cert: process.env.DEV_SSL_CERT ? fs.readFileSync(process.env.DEV_SSL_CERT) : null,
        },
        port: process.env.DEV_SERVER_PORT,

        // Fix bug caused by webpack-dev-server 3.1.11.
        // https://github.com/vuejs/vue-cli/issues/3173#issuecomment-449573901
        disableHostCheck: true,

        public: process.env.VUE_APP_PUBLIC_PATH,
        watchContentBase: true,
        watchOptions: {
            poll: 1000,
            ignored: /node_modules/,
        }
    },
    publicPath: process.env.VUE_APP_PUBLIC_PATH,
    configureWebpack: {
        plugins: [
            new ManifestPlugin({
                publicPath: '/'
            }),
        ],
        resolve: {
            alias: {
                'vue$': 'vue/dist/vue.esm.js'
            }
        }
    },
    chainWebpack: config => {
        // Remove the standard entry point
        config.entryPoints.delete('app')

        // Add entry points
        config.entry('app')
            .add('./src/js/app.js')
            .end()
            .entry('site')
            .add('./src/js/site.js')
            .end()

        // Preserve whitespace
        config.module
            .rule('vue')
            .use('vue-loader')
            .loader('vue-loader')
            .tap(options => {
                options.compilerOptions.preserveWhitespace = true
                return options
            })
    },
}

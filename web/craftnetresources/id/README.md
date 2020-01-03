# Craft ID Resources

## Overview
Craft ID resources have two endpoints:

### Site
The **site** endpoint is being used when the user is not authenticated and mostly provides the login form.

- **URL:** https://id.craftcms.com/
- **Endpoint:** /src/js/site.js
- **App Type:** Vue App

### App
The **app** endpoint is being used when the user is authenticated and provides the Craft ID app.
- **URL:** https://id.craftcms.com/account
- **Endpoint:** /src/js/app.js
- **App Type:** Vue App

## Setup
1. Copy all of the `TWIGPACK_` environment variables from `/.env.example` to your `/.env` file.
2. Copy all of the environment variables from `/web/craftnetresources/id/.env.example` to your `/web/craftnetresources/id/.env` file.

Make sure the URLs and ports don’t conflict with other projects your might be running at the same time.

## Commands

### Install
    npm install
    
### Build for Development
    npm run serve

### Build for Production
    npm run build

### Lint
    npm run lint
    
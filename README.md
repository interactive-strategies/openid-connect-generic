# OpenID Connect Generic Client #
**Contributors:** [daggerhart](https://profiles.wordpress.org/daggerhart), [tnolte](https://profiles.wordpress.org/tnolte)
**Tags:** security, login, oauth2, openidconnect, apps, authentication, autologin, sso  
**Requires at least:** 4.9  
**Tested up to:** 5.7.1  
**Stable tag:** 3.8.5  
**Requires PHP:** 7.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A simple client that provides SSO or opt-in authentication against OAuth2, and specifically enhanced for Salesforce integration. Forked from https://github.com/oidc-wp/openid-connect-generic

## Description ##

This plugin allows authentication of WordPress users via an OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Please submit issues to the Github repo: TODO

## Installation ##

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

## Frequently Asked Questions ##

### What is the client's Redirect URI? ###

Most OAuth2 servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

### Can I change the client's Redirect URI? ###

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI
`https://example.com/openid-connect-authorize`.


## Changelog ##

### 3.8.5

* Fix: @timnolte - Fixes missing URL request validation before use & ensure proper current page URL is setup for Redirect Back.
* Fix: @timnolte - Fixes Redirect URL Logic to Handle Sub-directory Installs.
* Fix: @timnolte - Fixes to provide proper redirect user back for the openid_connect_generic_auth_url shortcode.

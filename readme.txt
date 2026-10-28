=== Hardenly ===
Contributors: felipepitol
Tags: security, hardening, xml-rpc, user enumeration, uploads
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Common security hardening with simple switches: block PHP in uploads, disable XML-RPC, stop user enumeration, hide the version and more.

== Description ==

Hardenly applies a set of well-known hardening measures that most sites should have, each behind its own switch. There is no firewall, no scanner and no account to create: it changes how WordPress behaves, and you can see and undo every change.

**What it can do**

* **Prevent code execution in uploads** – adds rules to `wp-content/uploads/.htaccess` that block PHP files, including `.phtml`, `.php5`, `.phar` and double extensions such as `image.php.jpg`. Rules from other plugins in the same file are kept.
* **Disable XML-RPC** – turns off every XML-RPC method, including `pingback.ping`, which is often abused for DDoS amplification, and removes the `X-Pingback` header.
* **Prevent user enumeration** – stops `?author=N` lookups and hides usernames from the REST API for visitors, the users sitemap and oEmbed responses. Authors and editors keep the author list in the block editor.
* **Generic login errors** – the login form no longer tells attackers whether a username exists. Registration errors are still shown.
* **Disable file editors** – removes the theme and plugin file editors from the dashboard.
* **Disable registration** – keeps "Anyone can register" off.
* **Block the "admin" username** for new registrations.
* **Hide username as display name** – users cannot save a profile whose public display name is their login.
* **Hide the WordPress version** from the page head and feeds.
* **Remove the X-Powered-By header** that reveals the PHP version.
* **Disable directory browsing** in the uploads folder.

A **Check Module Status** button on the settings page confirms that each measure is really in effect on your server, for example that the `.htaccess` rules are present, not only that the switch is on.

**Privacy**

Hardenly does not collect data, does not send anything to external servers and does not add cookies.

== Installation ==

1. Install Hardenly from **Plugins → Add New**, or upload the `hardenly` folder to `/wp-content/plugins/`.
2. Activate the plugin. All measures start enabled.
3. Go to **Settings → Hardenly** to turn individual measures off, then click **Check Module Status**.

== Frequently Asked Questions ==

= Does it work on Nginx? =

Most measures work on any server. Blocking PHP in uploads and disabling directory browsing rely on `.htaccess`, which Apache and LiteSpeed read but Nginx ignores. On Nginx, add an equivalent rule to your server configuration, for example:

`location ~* /wp-content/uploads/.*\.(php[0-9]?|pht|phtml|phar|phps)$ { deny all; }`

= Will it break my site? =

Each measure can be turned off on its own. Things to know before enabling them:

* Disabling XML-RPC stops the Jetpack connection and the legacy WordPress mobile apps.
* Disabling registration overrides the "Anyone can register" setting.
* Blocking PHP in uploads affects any plugin that executes PHP files stored in the uploads folder, which is rare and usually a red flag.

= What happens when I deactivate or delete the plugin? =

Deactivating removes the plugin's rules from the uploads `.htaccess` (other rules stay) and every other measure stops applying. Deleting the plugin also removes its settings.

= I used ProtectMyWordPress from GitHub. How do I move to Hardenly? =

Install and activate Hardenly. It imports your ProtectMyWordPress settings and replaces its `.htaccess` rules. Then deactivate and delete ProtectMyWordPress.

== Screenshots ==

1. Protections grouped by area, with a meter that shows how many are on.
2. The status check shows whether each protection is working.

== Changelog ==

= 1.2.0 =
* Renamed to Hardenly for the WordPress.org Plugin Directory. Settings and `.htaccess` rules from ProtectMyWordPress are migrated on activation.
* Uploads: rules are written in their own block even when `uploads/.htaccess` already exists. Before, an existing file meant no rules were added while the status still reported OK.
* Uploads: rules work on Apache 2.2 and 2.4 and also cover `.phtml`, `.php5`, `.phar` and double extensions. They are removed on deactivation.
* XML-RPC: pingbacks are now disabled too, and the `X-Pingback` header is removed.
* User enumeration: also covers the users sitemap, oEmbed, POST requests and lists such as `?author=1,2`. The posts list filter in the dashboard and the block editor author list work again.
* Generic login errors no longer hide registration errors.
* "Hide username as display name" now checks profile updates. Before, it read a field that the registration form does not have, so it never applied.
* The status check reports the state of each measure, translated, with clear labels.
* All strings are in English and ready for translation.

= 1.1 =
* Fixed the settings form, the AJAX nonce and an XSS issue in notifications.
* Options are sanitized on save and assets load only on the settings page.

= 1.0 =
* First release, as ProtectMyWordPress.

== Upgrade Notice ==

= 1.2.0 =
Fixes uploads protection on sites that already had an .htaccess file in uploads, and fully disables XML-RPC pingbacks. Recommended for all users.

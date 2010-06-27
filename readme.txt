=== Log Deprecated Notices ===
Contributors: nacin
Tags: deprecated, logging, admin, WP_DEBUG, E_NOTICE
Requires at least: 3.0
Tested up to: 3.1-alpha
Stable tag: trunk

Logs the usage of deprecated files, functions, and function arguments, and identifies where the deprecated functionality is being used.

== Description ==

This plugin logs the usage of deprecated files, functions, and function arguments. It identifies where the deprecated functionality is being used and offers the alternative if available.

WP_DEBUG is not needed, though its general usage is strongly recommended. Deprecated notices normally exposed by WP_DEBUG will be logged instead.

Please report any bugs to (andrewnacin.com, plugins at), or find me in IRC #wordpress-dev or on Twitter (@nacin).

This is beta software. It works, but there's a lot left on the todo. Have an idea? Let me know.

This plugin is uninstallable. It will remove log entries on uninstall.

== Installation ==

1. Upload `log-deprecated-notices.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. View the log in the 'Tools' menu, under 'Deprecated Calls'

This plugin is uninstallable. It will remove log entries on uninstall.

== Screenshots ==

1. Log screen.
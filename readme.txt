=== Log Deprecated Notices ===
Contributors: nacin
Tags: deprecated, logging, admin, WP_DEBUG, E_NOTICE, developer
Requires at least: 3.0
Tested up to: 3.1-alpha
Stable tag: trunk

Logs the usage of deprecated files, functions, and function arguments, and identifies where the deprecated functionality is being used.

== Description ==

This plugin logs the usage of deprecated files, functions, and function arguments. It identifies where the deprecated functionality is being used and offers the alternative if available.

This is a plugin for developers. WP_DEBUG is not needed, though its general usage is strongly recommended. Deprecated notices normally exposed by WP_DEBUG will be logged instead.

Please report any bugs to plugins at [andrewnacin.com](http://andrewnacin.com/), or find me in IRC #wordpress-dev or @[nacin](http://twitter.com/nacin) on Twitter.

This is beta software. It works, but there's a lot left on the todo (check out the Ideas tab). Have an idea? Let me know.

== Installation ==

1. Upload `log-deprecated-notices.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. View the log in the 'Tools' menu, under 'Deprecated Calls'

This plugin is will remove log entries when it is uninstalled and deleted.

== Screenshots ==

1. Log screen.

== Ideas ==

These are the various things on the @todo:

 * Finish internationalization.
 * Menu bubble letting you know you have notices you haven't looked at yet.
 * Plugin identification. Also, an unobstrusive notice on plugins page next to said plugin.
 * Bulk actions and the ability to clear the log (with checkbox for auto-purge older than 30 days).
 * Ability to filter on version number, type of functionality that is deprecated, file or plugin in which the deprecated functionality is used, etc.
 * Ability to mute a specific notice, such as one that is known.
 * Offer some kind of better multisite support.

Want to add something here? I'm all ears. plugins at [andrewnacin.com](http://andrewnacin.com/) or @[nacin](http://twitter.com/nacin) on Twitter.
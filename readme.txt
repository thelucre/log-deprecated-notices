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

This is beta software. It works, but there's a lot left on the todo (check out the Other Notes tab). Have an idea? Let me know.

== Installation ==

1. Upload `log-deprecated-notices.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. View the log in the 'Tools' menu, under 'Deprecated Calls'

This plugin is will remove log entries when it is uninstalled and deleted.

== Screenshots ==

1. Log screen.

== Ideas ==

These are the various things on the @todo:

 * Plugin identification. Also, an unobstrusive note on plugins page next to said plugins.
 * Perhaps the ability to auto-purge the log.
 * Ability to filter on file or plugin in which the deprecated functionality is used.
 * Offer some kind of better multisite support.

Want to add something here? I'm all ears. plugins at [andrewnacin.com](http://andrewnacin.com/) or @[nacin](http://twitter.com/nacin) on Twitter.

I will prioritize these tasks based on feedback, so let me know what you'd like to see.
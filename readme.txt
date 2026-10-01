=== GZSEO ===
Contributors: aminhashemy
Requires at least: 4.7
Tested up to: 7.0
Stable tag: 2.0.15
Requires PHP: 7.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GZSEO is an SEO assistant plugin designed to make content updates easier for SEO professionals.

== Description ==

GZSEO is a powerful SEO assistant plugin that offers incredible features. With this plugin, you can easily identify underperforming keywords and update content powered by ChatGPT

== Terms & Conditions ==

You can find detailed information regarding the terms and conditions for using the GZSEO plugin at the following link:
https://gzseo.in/terms/

== Privacy & Policy ==

Your privacy and security are always our top priority. You can review all details about this topic at the following link:
https://gzseo.in/privacy-policy/

== Installation ==

1. Download the plugin from the WordPress repository or manually.
2. Upload the compressed file, install, and then activate it.
3. Go to GZSEO settings and connect your site to Google Search Console.

== Frequently Asked Questions ==

= Do I need to connect to Google Search Console?
Yes, to fully utilize the plugin's features, you need to connect your site to Google Search Console.

== External Services ==

https://gzseo.in
To retrieve Google Search Console queries, a confirmation request is sent to gzseo.in. No data is stored on this site; it only acts as an intermediary between your site and Google Search Console. 

For more details, please review our Terms and Conditions and Privacy Policy pages.

Terms & Conditions:
https://gzseo.in/terms/

Privacy Policy:
https://gzseo.in/privacy-policy/

== Changelog ==
= 2.0.15 =
* Add Cache For Productlist Schema
* Security: Fixed Broken Access Control vulnerability (CWE-862) reported by Patchstack affecting versions <= 2.0.14
* Security: All AJAX endpoints now enforce both nonce verification and capability check via GZSEO_Security_Helpers::verify_ajax_request()
* Security: Added missing capability checks to 13 AJAX endpoints (gzseo_save_settings, gzseo_refresh_dashboard, gzseo_auth_google, gzseo_update_ai_credits, gzseo_load_keywords_section, gzseo_get_saved_contents, gzseo_get_tables, gzseo_search_videos, gzseo_get_video_updates, gzseo_get_taxonomy_video_updates, gzseo_run_migration, gzseo_test_comment_ajax)
* Security: Disabled dangling gzseo_publish_comment AJAX hook (handler method did not exist, would cause fatal error)
* Security: Normalized ajax_run_migration and ajax_rollback_migration to use centralized security helper
* Compatibility: Resolved all WordPress Plugin Check warnings (WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery, WordPress.WP.EnqueuedResourceParameters.MissingVersion)
* Compatibility: Replaced direct $wpdb->delete call in product list schema cache invalidation with delete_metadata() API
* Compatibility: Added explicit version parameter to wp_register_style for gzseo-table-inline stylesheet
* Compatibility: Added phpcs:ignore annotations with justifications for all legitimate direct DB calls in migration files and complex JOIN queries
* Compatibility: Documented meta_query usage in comment generator with phpcs:ignore annotations (no WordPress API alternative exists)

= 2.0.14 =
* Fixed Schema Bugs

= 2.0.13 =
* Add Some Missing Files

= 2.0.12 =
* Security Enhancement: Implemented comprehensive nonce and capability verification
* Security Enhancement: All AJAX handlers now verify permissions BEFORE reading user input
* Security Enhancement: Added centralized security helper class for consistent validation
* Security Enhancement: Improved input sanitization and validation across all endpoints
* Code Quality: Refactored security checks to follow WordPress.org plugin standards
* Code Quality: Enhanced code maintainability with standardized security patterns

= 2.0.11 =
* Fixed the issue with timeouts in the bulk update.
* Added English translation to the plugin.

= 2.0.10 =
* Fixed the Toman currency issue in the carousel schema

= 2.0.9 =
* Fixed Conflict With Woodmart Theme Error

= 2.0.8 =
* Added the ability to provide a payment receipt
* Added the ability to register plugin updates for categories
* Added the ability to create comments only with female or male names
* Added the ability to change the password and recover the user account
* Fixed the problem of registering the message "low inventory" or similar when performing a mass update
* Added a random tone for generating comments
* Added the ability to edit the table after its final saving

= 2.0.7 =
* Add an add word button to the plugin's comment generation section.
* Fixed an issue with scrolling of content creation tabs in the plugin in mobile mode
* Fixed the issue of fixed hours, minutes and seconds when selecting random date generation when creating a comment.

= 2.0.6 =
* Fixed the issue of calling table styles on all pages of the site.
* Fixed the issue of page scrolling when clicking on links inside the product and article edit page

= 2.0.5 =
* Fixed an issue where some sites were experiencing early timeouts due to settings applied to other site plugins.
* Fixed the problem of loading the package list after logging in to the AI account.

= 2.0.4 =
* Fixing a fatal error in the index image generation section
* Fixing the issue with the AI-generated content edit button

= 2.0.3 =
* Enable inventory updates after bulk update
* Fixed an issue with saving professional settings information in Search Console

= 2.0.2 =
* Fixing the error when purchasing an AI account and returning to the site
* Fixing the problem of displaying an error message when the email is duplicated or invalid when registering an account in the plugin
* Added account balance update after completing a purchase
* Solving the problem of generating tables in bulk updates
* Solving the problem of not displaying the content generated with bulk updates

= 2.0.1 =
* Fixed a problem with generating a featured image that existed in some versions of WordPress
* Added a product carousel schema to product categories with the ability to enable and disable

= 2.0.0 =
* Fixed excessive server requests issue
* Fixed excessive server requests issue
* Fixed site errors when plugin server is unavailable
* Fixed plugin files loading on all admin panel pages
* Improved plugin dashboard performance
* Enabled table styling settings section
* Added image dimensions to featured image generation section
* Improved bulk update functionality
* Improved bulk update reports section
* Added data migration capability from previous version to new version
* Moved all plugin features to a single metabox
* Added multiple generation capability for meta titles and descriptions with best selection option
* Added capability to save generated comments from the past 30 days randomly
* Added capability to save generated comments as drafts
* Added capability to save generated comments with automatic activation at specified time
* Improved search console keywords display in comment generation section
* Added manual keyword addition capability for comment generation
* Added AI-generated table content editing capability
* Added row and column addition capability to AI-generated tables
* Added multiple video storage capability for single posts

= 1.0.12 =
Added feature to generate seo title and descriptions with compatibility to save on yoast and rankmath plugins
Added feature to generate comment and update contents base on content title only
Added feature to generate person schema
Updated rank tracker ui/ux

= 1.0.11 =
Added feature to generate products carousel schema
Added feature to generate post  image
Added feature to generate videos for post content updates
Added feature Rank Tracker

= 1.0.10 =
Added feature to change comment authors names
Added random tone in creating comments
Added transactions history for ai account
Added multiple content supports in Content-Updater Module
Added feature to Content-Updater Module to support creating contents based on keywords and some more extra settings.
Fixed AI login issue with digits plugin

= 1.0.9 =
Added UI Settings For Bulk Content Updater
Added Reports For Bulk Content Updater

= 1.0.8 =
* Added Keyword Research

= 1.0.7 =
* Added Bulk Content Updater
* Fixed Bulk update by taxonomies issues

= 1.0.6 =
* Added Bulk update by taxonomies
* Fixed AI panel auth problems
* Updated notifications for ajax events
* Updated UI in single post type metaboxes

= 1.0.5 =
* Remove eco ai tokens
* Fix bulk update conflict with no keyword posts
* Fix bulk update with to support more requests

= 1.0.4 =
* Make all assets versions dynamicly
* Fix preveiw button of posts before any bulk update actions
* Fix Google Search Console urls conflicts with wordpress default permalink generator for utf-8
* Testing plugin for wordpress 6.6
* Testing plugin for wordpress 6.7

= 1.0.3 =
* Improved the user interface on the bulk update and plugin configuration pages
* Fixed the conflict with the Rank Math SEO plugin
* Ability to choose the display location of the created table.
* Testing plugin for wordpress 6.6.2
* Testing plugin for wordpress 6.7.1

= 1.0.2 =
* Fixed payment errors.
* Deleted AI cache.
* Fixed bulk update query.

= 1.0.1 =
* Fixed "no keywords" error for posts in bulk comment generator.

= 1.0.0 =
* Published the first version.

== Upgrade Notice ==
Improved the user interface on the bulk update and plugin configuration pages
Fixed the conflict with the Rank Math SEO plugin
Ability to choose the display location of the created table
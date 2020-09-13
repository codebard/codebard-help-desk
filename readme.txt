===  CodeBard Help Desk ===
Author URI: https://codebard.com
Plugin URI: https://codebard.com/codebard-help-desk-for-wordpress/
Contributors: CodeBard
Donate link: https://codebard.com/donate
Tags: support ticket system, support tickets, help desk, support system, ticket system, customer service, help desk system, customer service, tickets
License: GPLv2
Requires at least: 4.0
Tested up to: 5.4.2
Stable Tag: 1.0.8

Multi Language Professional Support Ticket System with Unlimited Users, Unlimited Tickets, Unlimited Departments, Agents and many features

== Description ==

Provide professional support to users in your own language with CodeBard Help Desk. 
<br /><br />
<a href="http://help-desk-demo.codebard.com" target="_blank">Check out the Live Demo here</a>
<br /><br />
Its professional features include:
<ul>
<li>Unlimited Users</li>
<li>Unlimited Agents</li>
<li>Unlimited Tickets</li>
<li>Unlimited Departments</li>
<li>Multi Language</li>
<li>Easy Language translation from WP admin</li>
<li>English, Spanish (Intl.), Indian (Hindu) languages included</li>
<li>Front End Tickets</li>
<li>Front End Interface for Users</li>
<li>Front End Interface for Support Admins and Agents</li>
<li>Easy to Use</li>
<li>Private Tickets</li>
<li>Email Notifications</li>
<li>Ticket and Reply Attachments</li>
<li>Support Admins, Support Agents with separate permissions</li>
<li>Easy ticket management</li>
<li>Responsive</li>
<li>Extensible</li>
<li>Compatible with all Themes and Plugins</li>
<li>Adapts to visuals of any Theme</li>
<li>Hooks and Filters</li>
</ul>
In the <a target="_blank" href="http://help-desk-demo.codebard.com/">Live Demo</a>, try changing the theme from Theme Switcher widget on the right column to see how CodeBard Help Desk adapts to different themes!
<br />
<br />
Use Language admin to easily translate your plugin into your own language or change your own language's translation. New language packs will be upcoming! If you would like to contribute a translation for your own language, <a href="https://codebard.com/cb_p3_en-US.zip">download this sample language file</a> to translate and then contact us from our <a href="https://codebard.com/contact" target="_blank">contact page</a> to get credited.
<br />
<br />
Quickstart Guide is <a href="https://codebard.com/codebard-help-desk-for-wordpress-quickstart-guide" target="_blank">here</a> and detailed manual is <a href="https://codebard.com/codebard-help-desk-for-wordpress-manual" target="_blank">here</a>. All settings in your Plugin admin has detailed and easy to understand descriptions.  
<br />
<br />
Free support is available through Plugin forum, and Premium support is available through <a href="https://codebard.com/support" target="_blank">CodeBard site</a>.
<br />
<br />
If you have a WooCommerce store, get the <a href="https://codebard.com/codebard-help-desk-woocommerce-integration" target="_blank">WooCommerce Integration Addon</a> to power up your Help Desk and provide professional support to your store's customers easily:
<br />
<br />
Remember to follow us on Twitter (@codebardcom) or join our Newsletter by your Quickstart Wizard to hear about new features, announcements and goodies!

== Installation ==

1. Activate the plugin
2. A "CB Help Desk" menu item will be added to your WordPress Admin Menu. You can access your settings, departments and agents from there.
3. A welcome screen will get you quick-started.
4. That's it!

== Frequently Asked Questions ==

To be updated

== Screenshots ==

1. User Ticket View
2. Ticket creation by User on Front End
3. Admin Ticket View
4. Admin Ticket Listing
5. Support Agent to Department assignments
6. Languages admin

== Changelog ==

= 1.0.8 =

* Removed unused plugins_api hooked function

= 1.0.7 =

* Widget constructors are now PHP 7 compatible
* Important issue that prevented saving and using of modified languages fixed

= 1.0.6 =

* Ticket reference numbers in Ticket notification emails
* Buttons now use HTML5 method of wrapping links around them
* onclick action attribute removed from buttons
* Added error notification for cases in which ticket creation failed at WordPress post insert stage
* Fix for ticket attachment images ending up displaying as pages
* Fix for ticket title and replies causing errors when quotes or other characters are entered

= 1.0.5 =

* Major bugfix non-English (en-US) language sites and Out of Memory errors

= 1.0.4 =

* Major log display bug fix

= 1.0.3 =

* PHP Notice Fixes
* Bugfix for ticket status display in agent/admin ticket view
* Better permission checking when changing ticket department

= 1.0.2 =

* New core functions
* Enhanced upgrade and activation routine
* Variable handling fix in main runner function
* Var added to core for directory name
* Minor language fixes
* Woocommerce integration capability

= 1.0.1 =

* Language now merged by values read from language file
* Duplicate version number in default internal vars fixed
* Duplicate roles array removed from plugin/default_internal_vars.php
* Function name corrections
* Error handling corrections
* New function to internally update settings
* Major correction for the way apply_filters is used in core engine
* Skip department selection if there exists single department enhancement
* Do log correction for listing serialized variables
* Removed checking for non-false $return in core to allow filtering of results which return false
* Default internal table now has post_type and type fields
* Check for activated plugin for setup wizard redirect to prevent redirection in activation of other plugins
* Remove requiring admin status for admin init to prevent ajax issues

= 1.0.0 =

* Initial release!

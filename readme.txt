=== Bubs' Button Board ===
Contributors: bubblessoc
Donate link: http://bubblessoc.net/contact/
Tags: plugboard, plugs, links, sidebar, widget
Requires at least: 2.5
Tested up to: 2.7
Stable tag: 2.2

A customizable plugboard (http://plugboard.org) for your Wordpress 2.5+ blog.

== Description ==

Bubs' Button Board, a customizable [plugboard](http://plugboard.org) for your Wordpress blog, features optional button moderation and cache.  If the version of your Wordpress blog is < 2.5, you need to download an earlier version of this plugin ([found here](http://bubblessoc.net/archives/bubs-button-board/)).

== Installation ==

* Upload the `button-board` folder to `wp-content/plugins`.
* Activate the *Bubs' Button Board* plugin.
* Visit Wp-Admin > Settings > Button Board to edit your plugboard options.
* Make the `button-board/cache` directory writable!

== Upgrading from 1.0/1.1/2.0/2.1 ==

* Upload `button-board.php` to the folder `wp-content/plugins/button-board`, overwriting the original file

== Usage ==

* Create a Wordpress page for your plugboard (Write > Write Page)
* Paste `[bbb_page_print]` in the *Page Content* textarea.  Publish your page.
* If you want to include a mini-plugboard in your sidebar, use the following function:

	`<?php bbb_include(6, "<li>", "</li>"); ?>`
	
* The function parameters are: 
	1. The number of buttons you want to display (optional)
	2. The *Before* tag (optional)
	3. The *After* tag (optional)
* Alternatively, you can use the new mini-plugboard widget.
  
== Stying the Board ==

If you want your buttons to appear side-by-side, use the following CSS:

	ul#plugboard {
		/* The Plugboard Container */
		list-style: none;
		float: left;
	}
	
	ul#plugboard li {
		float: left;
		padding-right: 5px;
		padding-bottom: 5px;
	}
	
	#plugboard-form {
		/* The Plugboard Form Container */
		clear: both;
	}

For specific styling issues, please email me.

== Changelog ==

* **February 10, 2009** *(Version 2.2)*
  Validated compatibility with Wordpress 2.7.
  Optional `target="_blank"` added to button links.
  Fixed a problem that occurred when converting characters to HTML entities.

* **July 23, 2008** *(Version 2.1)*
  Validated compatibility with Wordpress 2.6.
  Added email option.
  Enabled button editing.

* **May 5, 2008** *(Version 2.0)*
  Plugin now compatible with Wordpress 2.5+.
  Also, added widget support.

* **July 5, 2007** *(Version 1.1)*
  Added support for servers with PHP `allow_url_fopen` disabled.
  This version requires a writable directory and [cURL](http://us.php.net/manual/en/ref.curl.php) (for error checking)

== Demo ==

[BubblesSOC Plugboard](http://bubblessoc.net/plugboard/)

== Comments, Problems, Suggestions? ==

Please email me: me@bubblessoc.net

== Plugin User? ==

If you are using my plugboard plugin on your website, let me know! You'll get a [special link](http://bubblessoc.net/plugboard/#plugin-users) on my plugboard page.

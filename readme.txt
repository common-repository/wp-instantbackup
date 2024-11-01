=== WP-InstantBackup ===
Contributors: krciga22, nickyoung
Donate link: http://blog.cyberkai.com/?page_id=20
Tags: instant, backup, ftp backup, email backup, email, ftp, password backup, remote backup, remote, database backup, db backup, local backup
Requires at least: 3.0
Tested up to: 3.1
Stable tag:trunk

WP-InstantBackup makes it simple to perform database and/or directory backups via FTP, Email, or both.

== Description ==
* Choice of Backup Method – FTP/Email/both.
* Choice of Backup Type – Database Backup/File System Backup/Full Backup
* Backup Selection List – Specify specific files and folders to backup – NEW FEATURE !!
* WP Admin Instant backup – perform a backup from WordPress Admin interface.
* URL Instant Backup – perform a backup by visiting a public URL (containing secret hash key).
* Password Protected ZIP files – in-case the backup falls in the wrong hands.
* Custom backup file names – specify the prefix of output ZIP file.
* Improved Interface with Green and Red notifications to make sure your settings are right.

== License ==

Copyright 2011 by Andrew Forster & Nick Young

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, you can download a copy from http://www.gnu.org/licenses/licenses.html

== Installation ==

1. Upload the WP-InstantBackup plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit the Instant Backup menu link in the left side menu
4. Fill out all the settings options you need
5. When your ready to perform a backup, there's a menu in the upper right hand corner of the wordpress admin. Click on either DB, Filesystem, or Full to perform a backup.

== Frequently Asked Questions ==
= How do I perform a backup? =
Once activated, the plugin should automatically place a menu in the top right of your wordpress admin with the three backup options.
= What is a database backup? =
A database backup is a backup of the current wordpress database--resulting in a zipped sql file which can be imported into mysql later to recover your database.
= What is a filesystem backup? =
A filesystem backup is simply a backup of a directory that you have specified within the Backup Output Settings. By default the Wordpress root directory is backed up.
= What is a full backup? =
A full backup performs both a filesystem and database backup, and generates two output zip files.
= I can't find the InstantBackup settings menu or access the InstantBackup settings page? =
Only administrator users are allowed to view the InstantBackup settings menu and page.

== Screenshots ==

1. WP-InstantBackup Wordpress Administration Interface
SilverStripe Versioned Files Module
===================================

Maintainer Contacts
-------------------
*  Andrew Short (<andrewjshort@gmail.com>)

Requirements
------------
* SilverStripe 2.4+

Documentation
-------------
[GitHub Wiki](http://wiki.github.com/ajshort/silverstripe-versionedfiles)

Installation Instructions
-------------------------

1. Place this directory in the root of your SilverStripe installation.
2. Visit yoursite.com/dev/build to rebuild the database.
3. Visit yoursite.com/dev/tasks/FileVersionCreationTask - this creates initial
   versions for any existing files.

Usage Overview
--------------
When you view a file in the Files & Images section of the CMS, you will notice
that two tabs have been added - "History" and "Replace".

The History tab contains a listing of all versions of the file, complete with
links. You can replace the file with an upload from your computer in the replace
tab.

Once you have created more than one version of a file, you have the option to
rollback to a specific version in the Replace tab.

Known Issues
------------
[Issue Tracker](http://github.com/ajshort/silverstripe-versionedfiles/issues)
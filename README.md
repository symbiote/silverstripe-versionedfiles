SilverStripe Versioned Files Module
===================================

[![Build Status](https://travis-ci.org/symbiote/silverstripe-versionedfiles.svg?branch=master)](https://travis-ci.org/symbiote/silverstripe-versionedfiles)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

**IMPORTANT**: File versioning is [built-in](https://docs.silverstripe.org/en/4/developer_guides/files/file_management/#file-versioning) with SilverStripe 4.x. There is currently no migration path from this module.

Requirements
------------
* SilverStripe 3.1 +

Installation Instructions
-------------------------

1. Place this directory in the root of your SilverStripe installation.
2. Visit yoursite.com/dev/build to rebuild the database.
3. Visit yoursite.com/dev/tasks/FileVersionCreationTask - this creates initial
   versions for any existing files.
   
Documentation
-------------
[User guide](docs/en/userguide/index.md)

Known Issues
------------
[Issue Tracker](http://github.com/ajshort/silverstripe-versionedfiles/issues)

Contributing Translations
-------------------------

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-versionedfiles](https://www.transifex.com/projects/p/silverstripe-versionedfiles) to contribute translations, rather than sending pull requests with YAML files.

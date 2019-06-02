phpBB 3.0.x Static HTML Converter

This tool will convert a phpBB 3.0.x based forum into a set of static HTML files.

Only MySQL based forums are supported by default, but others can easily be added
by copying the database drivers from the phpBB core files. They aren't included
simply to limit the number of files shipped by this tool.

Depending on the size of the board, PHP's default execution time may be reached.
The script attempts to set this to infinite, but if it can't, you will need to
update this yourself. This script is a one-shot script and cannot resume from an
interruption.

While this script can be executed through a webserver, it was designed with the
intent of being running on the command line.


Requirements
============
PHP 4.3.3+, 5+ (PHP 7 is not tested, but unlikly to work)
phpBB database connection (phpBB files are not required)

What Gets Converted
===================
Publicly viewable posts
BBCodes
Attachments are converted to direct links
Local and gallery avatars are converted to direct links

Limitations
===========
Links to forum topics within posts from users are not updated to use the .html suffix.
Specifying non-public forums may not convert since there are some authentication checks.
Search is no longer possible.
If the original style is not modified, some artifacts may appear due to features being 
unsupported like the jumpbox, memberlist, active topics, search, profiles and others.

How to Use
==========
1. Update "config.php"
    a. Update database connection information
    b. Update or remove the $style_data entries on lines 22 - 33
    c. Specify path to output location ($out_file)
    d. Specify forums to convert ($forums)
        i. Use array() to convert all guest viewable forums
2. Make "cache/" and output directories writable
3. Run convert.php
4. Copy the "images/" folder from the board to the output folder
5. Copy the "files/" folder from the board to the output folder
6. Copy the "styles/" folder from the board to the output folder
    a. If more than one style is installed, only the used style needs to be copied


License
=======
This tool uses files and code from phpBB 3.0.14 and therefor falls under its license, GPL v2.

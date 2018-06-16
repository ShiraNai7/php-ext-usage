PHP extension usage
###################

Find out which PHP extensions your code uses.

.. contents::


Requirements
************

- PHP 7.1+


How does it work
****************

This tool parses PHP code and attempts to find all used functions, constants
and classes that are not part of the PHP core. This is achieved using
`nikic/php-parser <https://github.com/nikic/PHP-Parser>`_
and `PHP's reflection <http://php.net/manual/en/book.reflection.php>`_.

You can then use this information to explicitly document or define your
dependencies (e.g. in *composer.json*).

See `Scanning directories and/or files`_ and `Getting Composer requirements`_.


Known limitations
=================

- cannot detect usages of extensions that are not installed at the time
  the tool is run
- cannot detect dynamic or indirect usages, e.g. variable class names,
  ``eval()``, etc. (although plain string callbacks, for example:
  ``call_user_func('json_encode')`` or ``array_map('mb_strtolower', ...)``,
  will be detected)


Installation
************

Download the Phar
=================

Phar archives are available with each release at https://github.com/ShiraNai7/php-ext-usage/releases

You can also build your own. See `Building the Phar`_.


Using Composer
==============

Globally:

.. code:: bash

   composer global require shira/php-ext-usage

Or as a dependency:

.. code:: bash

   composer require shira/php-ext-usage


Usage
*****

::

  php-ext-usage scan [options] [--] [<paths>...]

  Arguments:
    paths                        directories and/or files to scan

  Options:
    -o, --output=OUTPUT          output type (text or json or composer) [default: "text"]
    -e, --extension[=EXTENSION]  file extensions to scan [default: ["php"]] (multiple values allowed)
    -p, --progress               list file paths as they are scanned


Scanning directories and/or files
=================================

Scan all *.php* files in the given directories and/or files:

.. code:: bash

   php-ext-usage scan src

Example output:

::

  json
  ====

   * constant JSON_PRETTY_PRINT in src\Command\ScanCommand.php @ 178, 205
   * function json_encode in src\Command\ScanCommand.php @ 178, 205

  mbstring
  ========

   * function mb_strtolower in src\Command\ScanCommand.php @ 201


Getting Composer requirements
=============================

Set the ``--output`` option to ``composer`` to get a JSON output ready to be used
in your *composer.json*:

.. code:: bash

   php-ext-usage scan --output=composer /path/to/your/project/src

Example output:

.. code:: json

   {
       "require": {
           "ext-json": "*",
           "ext-mbstring": "*"
       }
   }


Getting a JSON report
=====================

Set the ``--output`` option to ``json`` to get a JSON output with all found
extension usages.

.. code:: bash

   php-ext-usage scan --output=json /path/to/your/project/src

Example output:

.. code:: json

   {
       "json": [
           [
               {
                   "file": "src\\Command\\ScanCommand.php",
                   "type": "constant",
                   "name": "JSON_PRETTY_PRINT",
                   "lines": [
                       178,
                       205
                   ]
               },
               {
                   "file": "src\\Command\\ScanCommand.php",
                   "type": "function",
                   "name": "json_encode",
                   "lines": [
                       178,
                       205
                   ]
               }
           ]
       ],
       "mbstring": [
           [
               {
                   "file": "src\\Command\\ScanCommand.php",
                   "type": "function",
                   "name": "mb_strtolower",
                   "lines": [
                       201
                   ]
               }
           ]
       ]
   }


Building the Phar
*****************

Use the *build-phar.sh* script (available in source). You need to have
`Box <https://github.com/humbug/box>`_ installed
(either globally or as *box.phar* in the project's root directory).

.. code:: bash

   bin/build-phar.sh

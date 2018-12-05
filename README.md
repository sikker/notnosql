NotNoSQL
========

What it is:
-----------------
NotNoSQL is an SQL-based NoSQL key/value store. What that means is that like bigger projects like Couch or Mongo, it stores data using key/value pairs rather than following a table/row/column scheme of traditional SQL-based RDBMS's. It also means that it uses such an SQL-based RDBMS to achieve such functionality. 

What it isn't:
-----------------
NotNoSQL is not intended for large deployments, nor is it intended to be a replacement for dedicated NoSQL-based key/value stores. Rather, it is intended as a quick way to gain data persistency in smaller applications with some concurrency (which would render a .json file dangerous to rely on). Think of it less as a database alternative and more as a flat-file-storage alternative.

Example of usage:
-----------------

```php
<?php

require 'vendor/autoload.php';
$notnosql = new Sikker\NotNoSQL\NotNoSQL( new PDO('sqlite:database.sqlite3') );
$notnosql->put('foo', 'bar');
echo $notnosql->get('foo') . PHP_EOL;
```

How it works:
-----------------
NotNoSQL works by feeding it a PDO object connected to whatever datasource you want to use, be it an sqlite database or an Oracle cluster. It has been tested to work with SQLite3 and MySQL. After that, you just treat it like a json-based key/value store like so:

```php
$notnosql->put('beef.stroganoff.attr.country', ['unknown', 'unknown', 'THE EMPIRE!']);
var_dump($notnosql->get('beef.stroganoff.attr.country'));
```

Simple, eh?

A thing to keep in mind is that the data is converted to arrays internally, even if it's entered as objects. You will thus always get arrays back out. Feel free to cast/convert them to stdClass objects as you need to after that though!

Contents put into multidimensional arrays like so:

```php
$notnosql->put('beef.stroganoff.chef', 'Bender');
$notnosql->put('beef.stroganoff.customer', 'Fry');
```

Can be fetched starting from any parent you wish, like so:

```php
$notnosql->get('beef.stroganoff'); // returns ['chef' => 'Bender', 'customer' => 'Fry']
```

If you want to add items to an existing array, you can use the add method:

```php
$notnosql->put('deliveries', []);
$notnosql->add('deliveries', ['from' => 'Nixon', 'to' => 'Lrrr, Ruler of Omicron Persei 8']);
$notnosql->add('deliveries', ['from' => 'Russia', 'with' => 'love']);
```

You can delete a key and everything below it with the delete method:

```php
$notnosql->delete('beef.stroganoff');
```

Although if you just want to delete the contents, you should simply use put to give it an empty value again:

```php
$notnosql->put('deliveries', []);
```
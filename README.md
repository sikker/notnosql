NotNoSQL
========

Simplicity is king. NotNoSQL is not intended for large deployments, nor is it intended to be a replacement for dedicated NoSQL-based key/value stores. Rather, it is intended as a quick way to gain data persistency in smaller applications with some concurrency (which would render a .json file dangerous to rely on). 

How it works:
-----------------
NotNoSQL works by feeding it a PDO object connected to whatever datasource you want to use, be it an sqlite database or an Oracle cluster. It has been tested to work with SQLite3 and MySQL. After that, you just treat it like a json-based key/value store like so:

   $notnosql->put('beef.stroganoff.attr.country', array('unknown', 'unknown', 'THE EMPIRE!'));
   var_dump($notnosql->get('beef.stroganoff.attr.country'));

A thing to keep in mind is that by default, the JSON objects are returned as associative arrays. This is in part because that is the most likely scenario for you to try and store data in, and secondly because associative arrays resolve a teeeeensy bit faster than stdClass objects do. But if you need this behaviour to be different, just call this method after construct:

   $notnosql->setJsonDecodePolicy(NotNoSQL::JSON_DECODE_POLICY_OBJECT);

It can be reversed with, you guessed it:

   $notnosql->setJsonDecodePolicy(NotNoSQL::JSON_DECODE_POLICY_ARRAY);

Simple, eh?

Keep in mind that this key/value store isn't particularly smart. It doesn't keep an index of your data or anything similar, so entering:

   $notnosql->put('beef.stroganoff.chef', 'Bender');
   $notnosql->put('beef.stroganoff.customer', 'Fry');

*Will not* allow you to get both out in an array with:

   $notnosql->get('beef.stroganoff');

If you need this behaviour, save them as an array instead:

   $notnosql->put('beef.stroganoff', array('chef' => 'Bender', 'customer' => 'Fry'));


Example of usage:
-----------------

	<?php

	include 'notnosql/notnosql.class.php';
   $notnosql = new NotNoSQL( new PDO('sqlite:database.sq3') );
   $notnosql->put('foo', 'bar');
   echo $notnosql->get('foo') . PHP_EOL;

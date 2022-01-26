# graphml_generator
PHP GraphML Generator

Install in your project with composer 

```
$ composer require ramoty/graphml_generator
```

Usage

Open ./vendor/ramoty/graphml_generator/graphml_generator.php

Change as needed:
* $sPathForSourceCodeFiles = dirname(__FILE__ ) . '/../../../'; // project root
* $graphResults = 'docs/graphResults'; // saved and created at the root of the project docs/graphResults
* $sPathForSourceCodeFilesExclude = ['vendor']; // exlude directories with any of those names

Execute
```
$ php ./vendor/ramoty/graphml_generator/graphml_generator.php
```

To view the generated $ graphResults file, visit https://www.yworks.com/yed-live/

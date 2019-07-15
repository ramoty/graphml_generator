# graphml_generator
PHP GraphML Generator

Install in your project with composer 

$ composer require ramoty/graphml_generator

Usage

Em ./vendor/ramoty/graphml_generator/graphml_generator.php

altere se necessario

$sPathForSourceCodeFiles = dirname(__FILE__ ) . '/../../../'; // project root

$graphResults = 'documentation/graphResults'; // saved and created at the root of the project documentation/graphResults

$sPathForSourceCodeFilesExclude = 'vendor'; // Delete the directory of the generation

Execute

$ php ./vendor/ramoty/graphml_generator/graphml_generator.php

to view the generated $ graphResults file, visit https://www.yworks.com/yed-live/

<?php
/**
 * GraphML Generator v0.1ba
 *
 * Requirements: PHP5
 *
 * GraphML Generator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, version 3 of the License, or
 * (at your option) any later version.
 *
 * 
 * GraphML is a very basic UML generator script and it is in beta status.
 * The script processes only .php files which has classes.
 * The generated UML can be viewed in yEd (http://www.yworks.com/en/products_yed_about.html)
 * or any graph editor program that can read .graphml files.
 * 
 * If you have any questions don't hesitate to conctact me.
 * 
 * @package  GraphML Generator
 * @author   Janos Tucsek <tucsi@tucsi.hu>
 * @license  http://www.gnu.org/licenses/lgpl.html LGPL
 * @link     http://www.tucsi.hu/graphml/
 * @date     2010-02-19
 * @updated  2010-03-05

 * @updated  2017-10-26 by Juan David M. jdmejia2009@gmail.com 
 * added some changes in the order and size 
 * (width now if one clas has a method or atributte with too much characters the box expands to cover 
 * and the other classes if the resolution limit of 1k pixels is exceed it puts the next ones below) 
 * of the classes in the graph and changes the regular expresion for the methods and their params 
 * so it accepts params that pass by reference and some especial chars, and removed line jumps 
 * or tabulations from the string given by the function file_get_contents(filename),
 * and lets you establish an output directory too and create subdirectories in case the path doesnt exist recursivelly,
 * tough it has by defeault the output directory 
 * ./graphResults so the generated files are put in this folder next to this script 
 * tough this can be changed through the constructor params
 * added also a new edge template for associations with other classes through attributes
 * @updated 2017-10-31 
 * -Fixed variable names.
 * -Removed comments in a  different language, those wore transalted to english if they were useful.
 * -Removed debug echos and  the messages were styliced a little while the process is running,
 * -Fixed when two different files have the same class name by adding an incremental id before adding the class
 * to the aClassData array to avoid replacing the one found before and is informed through echo to the user of this rename 
 * -Fixed when in methods the & is detected but when added is removed
 */


error_reporting(E_ALL);
ini_set('display_errors', 1);


define('_MAX_NODE_HEIGHT', 50);
define('_MAX_NODE_PER_COLUMN', 5);

class generateGraphML
{
    protected $sDirectory; /* initial directory, there will be the graphml file stored */
    protected $iNodeYPos = 10;
    protected $iNodeNumber = 0;
    protected $iNodeRows = 1;
    protected $iNodeColumn = 1;
    protected $iMaxNodeHeight = _MAX_NODE_HEIGHT;

    protected $iEdgeNumber = 0;

    protected $sNodeTemplate;
    protected $sEdgeTemplate;
    protected $sFullTemplate;
    protected $sGeneratedNodes;
    protected $sGeneratedEdges;

    protected $aMethods;
    protected $aAttributes;
    protected $sClassName;

    protected $aEdgeData = array();
    protected $aClassData = array();



    protected $sBaseDir;
    protected $sTemplateDir = 'templates/';

    protected $aProcessedDirectories = array();

    //new 26/10/2017
    protected $iNodeXPosBefore = 50;
    protected $iLastNodeHeight = _MAX_NODE_HEIGHT;

    protected $sDirResultGlobal = '';

    protected $aEdgeData2OtherRelations = array();
    protected $sEdge2OtherRelationsTemplate;
    protected $sGeneratedEdges2OtherRelations;

    protected $bClassFound = false;
    protected $sMessageClassFound = '';


    /**
     * Constructor and executor
     *
     * @param string $sDirectory  Initial directory, this is the first dir where .php files will be searched
     * @param string $sExcludeDir Don't read directories matching $sExcludeDir
     */
    public function __construct($sDirectory = '.', $sDirResult = './graphResults', $sExcludeDirs = [''])
    {
        echo "Path for Generated .graphml files: <b>$sDirResult</b> <br>\n";
        $this->sBaseDir = dirname(__FILE__ ) . '/';

        $this->sDirectory = $sDirectory;

        $this->sDirResultGlobal = $sDirResult;

        /* Let's read the template files */
        if (is_file($this->sBaseDir . $this->sTemplateDir . 'node.tpl'))
        {
            $this->sNodeTemplate = file_get_contents($this->sBaseDir . $this->sTemplateDir . '/node.tpl');
        }
        else
        {
            echo 'Node template file not found, exiting.' . "\n";
            exit(1);
        }
        if (is_file($this->sBaseDir . $this->sTemplateDir . '/edge.tpl'))
        {
            $this->sEdgeTemplate = file_get_contents($this->sBaseDir . $this->sTemplateDir . '/edge.tpl');
        }
        else
        {
            echo 'Edge template file not found, exiting.' . "\n";
            exit(1);
        }
        if (is_file($this->sBaseDir . $this->sTemplateDir . '/full.tpl'))
        {
            $this->sFullTemplate = file_get_contents($this->sBaseDir . $this->sTemplateDir . '/full.tpl');
        }
        else
        {
            echo 'Full template file not found, exiting.' . "\n";
            exit(1);
        }
        //new 26/10/2017
        if (is_file($this->sBaseDir . $this->sTemplateDir . '/edge2OtherRelations.tpl'))
        {
            $this->sEdge2OtherRelationsTemplate = file_get_contents($this->sBaseDir . $this->sTemplateDir . '/edge2OtherRelations.tpl');
        }
        else
        {
            echo 'Edge Other Relations template file not found, exiting.' . "\n";
            exit(1);
        }

        /* Search for .php files */
        $this->walkDir($this->sDirectory, $sExcludeDirs);

        /* Create edges between objects inheritance */
        $this->createEdges();

        /* Create edges between objects associations by attributes with other classes*/
        $this->createEdges2OtherRelations();

        /* Finally generate the graphml file */
        $this->createFullGraphML();
    } //end constructor

    /**
     *
     * Process .php files in directory
     *
     * @param string $sDirectory
     */
    protected function collectFiles($sDirectory = '.')
    {
        if (isset($this->aProcessedDirectories[$sDirectory]))
        {
            return;
        }

        $this->aProcessedDirectories[$sDirectory] = true;
        foreach (glob($sDirectory . '/*.php') as $sCurrentFile)
        {
            $this->bClassFound = false;
            $this->sMessageClassFound = '';
            $this->sMessageClassFound .= 'Processing file: ' . $sCurrentFile . ' - ';
            $this->processFile($sCurrentFile);
            $this->sMessageClassFound .= ' - Done' . "<br>\n";
            echo $this->sMessageClassFound;
        }
    } //end function


    /**
     *
     * Walk recursively directories for .php files
     * 
     * @param string $sDirectory   Search for .php files in $sDirectory
     * @param string $sExcludeDir (optional) Don't read directories matching $sExcludeDir
     */
    protected function walkDir($sDirectory, $sExcludeDirs = [''])
    {
        if ($rDh = opendir($sDirectory))
        {
            $this->collectFiles($sDirectory);
            while (($sCurrentFile = readdir($rDh)) !== false)
            {
                if ($sCurrentFile == '.' || $sCurrentFile == '..')
                    continue;

                if (in_array($sCurrentFile, $sExcludeDirs))
                    continue;

                if (is_dir($sDirectory . '/' . $sCurrentFile))
                {
                    /* Current file is a directory, so entering into it */
                    $this->walkDir($sDirectory . '/' . $sCurrentFile, $sExcludeDirs);

                    /* Search and parse .php files in the given directory */
                    $this->collectFiles($sDirectory . '/' . $sCurrentFile);
                }
            }
            closedir($rDh);
        }
    } //end function


    /**
     *
     * Process .php file
     *
     * @param string $sFileName filename to process
     */
    protected function processFile($sFileName)
    {

        $this->sClassName = '';
        $this->aAttributes = array();
        $this->aMethods = array();

        if (!is_file($sFileName))
        {
            return false;
        }

        if ($sContent = file_get_contents($sFileName))
        {
            /* Remove comments, line jumps, tabulations, carriage return
             * (this was made in case parameters of a function are ordered by line jumps
             * so those are removed to help the regular expresion can grab all of them) */
            $sContent = preg_replace('/(\/\/.*)/i', '', $sContent);
            $sContent = preg_replace('/(\/\*(.*)\*\/)/imsU', '', $sContent);
            $sContent = str_replace(array("\n", "\t", "\r"), " ", $sContent);

            /* Search for a class name and extends if it exists */
            if (preg_match('/class ([a-z0-9\_]+)( extends ([a-z0-9\_]+))*/im', $sContent, $aMatchResult))
            {
                $this->sClassName = ucfirst(strtolower($aMatchResult[1]));

                /*when a class found in other file already exist with the same name as key of the array aClassData */
                $iContClassAlreadyExist = 1;
                while (isset($this->aClassData[$this->sClassName]) == true)
                {
                    echo "Class with name: <b>( " . $this->sClassName . " )</b> already exist in the collection of found classes of the processed files, renamed with the incremental ID <b>( $iContClassAlreadyExist )</b>.<br>";
                    $this->sClassName = ucfirst(strtolower($aMatchResult[1])) . '_' . $iContClassAlreadyExist;
                    $iContClassAlreadyExist++;
                } //end while

                /* Class has extends */
                if (!empty($aMatchResult[3]))
                {
                    echo "<b>Edge Found for inheritance</b>: Actual Class ( " . $this->sClassName . " ) asociated with other one of name ( " . $aMatchResult[3] . " ) by inheritance.<br>";
                    $this->aEdgeData[$this->sClassName] = ucfirst(strtolower($aMatchResult[3]));
                }

                $this->aClassData[$this->sClassName] = $this->iNodeNumber;
            }

            /* If it's not a class, then we won't work with it */
            if (empty($this->sClassName))
            {
                return;
            }

            /* Search for methods regardless of their accessibility (protected|public|private, etc) */
            if (preg_match_all('/function ([a-z0-9\_]+[\(][a-z0-9\_\(\)\$=\'\"\.\&\-\,\n\| ]+[\)]*)/im', $sContent, $aMatchResult))
            {
                foreach ($aMatchResult[1] as $sCurrentMethodName)
                {
                    /*in methods the & is detected but when added is removed*/
                    $this->aMethods[] = str_replace(array("\n", "&"), " ", $sCurrentMethodName);
                }
            }

            /* Search for properties */
            if (preg_match_all('/(protected|private|public|var) (static )*\$([a-z0-9\_\=\'\"\. ]+)/im', $sContent, $aMatchResult))
            {
                /*search inside attributes for associations, color:blue, no arrow */
                foreach ($aMatchResult[3] as $sCurrentAttributeName)
                {
                    $this->aAttributes[] = $sCurrentAttributeName;

                    if (preg_match_all('/([a-z0-9\_\. ]+)[\=][\s]*new[\s]*([a-z0-9\_\. ]+)/im', $sCurrentAttributeName, $arrMartchResAsocOtherClass))
                    {
                        foreach ($arrMartchResAsocOtherClass[2] as $sOtherClass)
                        {
                            /*if target class exist concatenates an incremental id*/
                            $sTempTargetClass = $this->sClassName;

                            $iCont = 1;
                            while (isset($this->aEdgeData2OtherRelations[$sTempTargetClass]) == true)
                            {
                                $sTempTargetClass = $this->sClassName . "%%sep%%" . $iCont;
                                $iCont++;
                            } //end if

                            if (isset($this->aEdgeData2OtherRelations[$sTempTargetClass]) == false)
                            {
                                echo "<b>Edge Found for simple association</b>: Actual Class ( " . $sTempTargetClass . " ) associated with other one ( " . $sOtherClass . " ) by attribute.<br>";
                                /* Class has asociations with other class */
                                $this->aEdgeData2OtherRelations[$sTempTargetClass] = ucfirst(strtolower($sOtherClass));
                            } //end if

                        } //end foreach

                    } //end if
                } //end foreach
            } //end if


        } //end if

        /* If it's not a class, then we won't work with it */
        if (!empty($this->sClassName))
        {
            $this->createNodes();
        }

        return;
    } //end function


    /**
     *
     * Ok, we have a parsed .php file, so let's create a node for it in the graph
     *
     */
    protected function createNodes()
    {
        $iNodeHeight = (count($this->aAttributes) + count($this->aMethods)) * 15 + 50;
        if ($iNodeHeight > $this->iMaxNodeHeight)
        {
            $this->iMaxNodeHeight = $iNodeHeight;
        }

        $iNodeWidth = 169.0;

        $iMaxLengthAttributeOrMethodName = 24;

        foreach ($this->aAttributes as $key => $sCurrentAttribute)
        {
            if ($iMaxLengthAttributeOrMethodName < strlen($sCurrentAttribute))
            {
                $iMaxLengthAttributeOrMethodName = strlen($sCurrentAttribute);
            } //end if
        } //end foreach

        foreach ($this->aMethods as $key => $sCurrentMethod)
        {
            if ($iMaxLengthAttributeOrMethodName < strlen($sCurrentMethod))
            {
                $iMaxLengthAttributeOrMethodName = strlen($sCurrentMethod);
            } //end if
        } //end foreach

        /* added strlen comparison with the class name too for the width calculation */
        if ($iMaxLengthAttributeOrMethodName < strlen($this->sClassName))
        {
            $iMaxLengthAttributeOrMethodName = strlen($this->sClassName);
        } //en if


        $iNodeWidth = 6 * $iMaxLengthAttributeOrMethodName;

        if ($this->iNodeColumn == 1)
        {
            $this->iNodeXPosBefore = 50;
        } //end if

        $sSurpassLimit = "NO";
        if ($this->iNodeXPosBefore > 1000)
        {
            /*if when the horizontal distance has surpassed the limit for placing the new class*/
            $this->iNodeYPos += $this->iLastNodeHeight + 50;
            $this->iNodeXPosBefore = 50;
            $this->iNodeColumn = 1;
            $this->iLastNodeRows = $this->iNodeRows;
            $this->iNodeRows++;
            $this->iMaxNodeHeight = _MAX_NODE_HEIGHT;
            $sSurpassLimit = "YES";
        } //end if 

        $iNodeXPos = $this->iNodeXPosBefore;

        $this->iNodeXPosBefore = $this->iNodeXPosBefore + $iNodeWidth + 50;

        $this->bClassFound = true;

        $this->sMessageClassFound .= "Id Node: <b>(" . $this->iNodeNumber . ")</b> Class Found: <b>" . $this->sClassName . "</b>";

        /* Added width variable and the node.tpl file was changed to reflect this addition */
        $aSearchFor = array('%%node_id%%',
                                '%%node_x%%',
                                '%%node_y%%',
                                '%%class_name%%',
                                '%%node_stereotype%%',
                                '%%node_attributes%%',
                                '%%node_methods%%',
                                '%%node_height%%',
                                '%%node_width%%'
                                 );
        $aReplaceWith = array($this->iNodeNumber++,
                                $iNodeXPos,
                                ($this->iNodeYPos),
                                $this->sClassName,
                                '',
                                implode("\n", $this->aAttributes),
                                implode("\n", $this->aMethods),
                                $iNodeHeight,
                                $iNodeWidth //se adiciono ancho
                                 );

        $this->iLastNodeHeight = $iNodeHeight;

        if ($this->iMaxNodeHeight > $this->iLastNodeHeight)
        {
            $this->iLastNodeHeight = $this->iMaxNodeHeight;
        } //end if  

        if ($this->iLastNodeHeight > $this->iMaxNodeHeight)
        {
            $this->iMaxNodeHeight = $this->iLastNodeHeight;
        } //end if 

        $this->sGeneratedNodes .= str_replace($aSearchFor, $aReplaceWith, $this->sNodeTemplate);

        /* If we reach the maximum node in the given row then "create" a new row */
        if ($this->iNodeNumber % _MAX_NODE_PER_COLUMN == 0 && $sSurpassLimit == "NO")
        {
            $this->iLastNodeRows = $this->iNodeRows;
            $this->iNodeRows++;
            $this->iNodeColumn = 0;
            $this->iNodeYPos += $this->iLastNodeHeight + 50;
            $this->iMaxNodeHeight = _MAX_NODE_HEIGHT;


        } //end if
        $this->iNodeColumn++;
    } //end function


    /**
     *
     * This method is for finding relations between classes and creates the appropriate edges
     *
     */
    protected function createEdges()
    {
        $this->sGeneratedEdges = '';
        foreach ($this->aEdgeData as $sTargetClassName => $sSourceClassName)
        {
            echo "Creating edges by inheritance. Target: <b>$sTargetClassName</b> Source: <b>$sSourceClassName</b><br>";
            if (!isset($this->aClassData[$sSourceClassName]) || !isset($this->aClassData[$sTargetClassName]))
            {
                echo "<b>Doesn't exist in the class data for inheritance edges</b><br>";
                continue;
            }

            $aSearchFor = array('%%edge_id%%',
                                   '%%source_node%%',
                                   '%%target_node%%');
            $aReplaceWith = array($this->iEdgeNumber++,
                                   $this->aClassData[$sSourceClassName],
                                   $this->aClassData[$sTargetClassName]);

            $this->sGeneratedEdges .= str_replace($aSearchFor, $aReplaceWith, $this->sEdgeTemplate);
        }
    } //end function

    /**
     *
     * This method is for finding relations between classes and creates the appropriate edges besides inheritance
     *
     */
    protected function createEdges2OtherRelations()
    {
        $this->sGeneratedEdges2OtherRelations = '';
        foreach ($this->aEdgeData2OtherRelations as $sTargetClassName => $sSourceClassName)
        {
            echo "Creating edges by association. Target: <b>$sTargetClassName</b> Source: <b>$sSourceClassName</b><br>";
            if (!isset($this->aClassData[$sSourceClassName]) || !isset($this->aClassData[$sTargetClassName]))
            {
                echo "<b>Doesn't exist in the class data for association edges in the attributes</b><br>";
                continue;
            }


            $aSearchFor = array('%%edge_id%%',
                                   '%%source_node%%',
                                   '%%target_node%%');
            $aReplaceWith = array($this->iEdgeNumber++,
                                   $this->aClassData[$sSourceClassName],
                                   $this->aClassData[$sTargetClassName]);

            $this->sGeneratedEdges2OtherRelations .= str_replace($aSearchFor, $aReplaceWith, $this->sEdge2OtherRelationsTemplate);
        } //end foreach


    } //end function


    /**
     *
     * Creates graphml file in initial directory
     *
     */
    protected function createFullGraphML()
    {
        $sContent = str_replace('%%nodes%%', $this->sGeneratedNodes, $this->sFullTemplate);
        $sContent = str_replace('%%edges%%', $this->sGeneratedEdges, $sContent);
        $sContent = str_replace('%%edges2%%', $this->sGeneratedEdges2OtherRelations, $sContent);


        $pathResults = $this->sDirResultGlobal;
        if (file_exists($pathResults) == false)
        {
            mkdir($pathResults, 0777, true);
        } //end if

        if ($rFp = fopen($pathResults . '/uml_' . date('YmdHis') . '.graphml', 'w'))
        {
            fputs($rFp, $sContent);
            fclose($rFp);
        }
    } //end function
} //end class


$sPathForSourceCodeFiles = dirname(__FILE__ ) . '/../../../'; // project root
$graphResults = 'docs/graphResults'; // saved and created at the root of the project docs/graphResults
$sPathForSourceCodeFilesExclude = ['vendor']; // exlude directories with any of those names
echo "Path For Source Code Files To Read: <b>$sPathForSourceCodeFiles</b><br>";

$oGraphMLGenerator = new generateGraphML($sPathForSourceCodeFiles, $graphResults, $sPathForSourceCodeFilesExclude);

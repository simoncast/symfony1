<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

/**
 * Doctrine_Record_Generator
 *
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @package     Doctrine
 * @subpackage  Plugin
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version     $Revision$
 * @link        www.doctrine-project.org
 * @since       1.0
 */
abstract class Doctrine_Record_Generator extends Doctrine_Record_Abstract
{
    /**
     * _options
     *
     * @var array $_options     an array of plugin specific options
     */
    protected $_options = array(
        'generateFiles'  => false,
        'generatePath'   => false,
        'builderOptions' => array(),
        'identifier'     => false,
        'table'          => false,
        'pluginTable'    => false,
        'children'       => array(),
        'cascadeDelete'  => true,
        'appLevelDelete' => false
    );

    /**
     * Whether or not the generator has been initialized
     *
     * @var bool $_initialized
     */
    protected $_initialized = false;

    /**
     * __construct
     *
     * @param string $options
     * @return void
     */
    public function __construct($options)
    {
        $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
    }

    /**
     * An alias for getOption
     *
     * @param string $option
     */
    public function __get($option)
    {
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
        return null;
    }

    /**
     * __isset
     *
     * @param string $option
     */
    public function __isset($option) 
    {
        return isset($this->_options[$option]);
    }

    /**
     * Returns the value of an option
     *
     * @param $option       the name of the option to retrieve
     * @return mixed        the value of the option
     */
    public function getOption($name)
    {
        if ( ! isset($this->_options[$name])) {
            throw new Doctrine_Exception('Unknown option ' . $name);
        }
        
        return $this->_options[$name];
    }

    /**
     * Sets given value to an option
     *
     * @param $option       the name of the option to be changed
     * @param $value        the value of the option
     * @return Doctrine_Plugin  this object
     */
    public function setOption($name, $value)
    {
        $this->_options[$name] = $value;
        
        return $this;
    }

    /**
     * Add child record generator 
     *
     * @param  Doctrine_Record_Generator $generator 
     * @return void
     */
    public function addChild($generator)
    {
        $this->_options['children'][] = $generator;
    }

    /**
     * Returns all options and their associated values
     *
     * @return array    all options as an associative array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Sets primary key for the table. Honors the Doctrine_Core::ATTR_DEFAULT_IDENTIFIER_OPTIONS
     * setting.
     *
     * @return void
     */
    public function setupPrimaryKey()
    {
        list($name, $type, $length, $definition) = $this->getIdentifierDefinition();
        $this->hasColumn($name, $type, $length, $definition, true);
    }

    /**
     * Returns an array of identifier options as an array:
     *
     *  - name
     *  - type
     *  - length
     *  - definition
     *
     * @return array
     */
    public function getIdentifierDefinition()
    {
        $identifierOptions = $this->getTable()->getAttribute(Doctrine_Core::ATTR_DEFAULT_IDENTIFIER_OPTIONS);

        $name = (isset($identifierOptions['name']) && $identifierOptions['name']) ? $identifierOptions['name'] : 'id';
        $name = sprintf($name, $this->getTable()->getTableName());

        $type = (isset($identifierOptions['type']) && $identifierOptions['type']) ? $identifierOptions['type'] : 'integer';
        $length = (isset($identifierOptions['length']) && $identifierOptions['length']) ? $identifierOptions['length'] : 8;

        $definition = array(
            'length' => $length,
            'autoincrement' => isset($identifierOptions['autoincrement']) ? $identifierOptions['autoincrement'] : true,
            'primary' => isset($identifierOptions['primary']) ? $identifierOptions['primary'] : true);

        unset($identifierOptions['name'], $identifierOptions['type'], $identifierOptions['length']);
        foreach($identifierOptions as $key => $value)
        {
          if(!isset($definition[$key]) || !$definition[$key])
          {
            $definition[$key] = $value;
          }
        }

        return array(
            $name,
            $type,
            $length,
            $definition
        );
    }

    /**
     * Initialize the plugin. Call in Doctrine_Template setTableDefinition() in order to initiate a generator in a template
     *
     * @see Doctrine_Template_I18n
     * @param  Doctrine_Table $table 
     * @return void
     */
    public function initialize(Doctrine_Table $table)
    {
      	if ($this->_initialized) {
      	    return false;
      	}
          
        $this->_initialized = true;

        $this->initOptions();

        $table->addGenerator($this, get_class($this));

        $this->_options['table'] = $table;

        $ownerClassName = $this->_options['table']->getComponentName();
        $className = $this->_options['className'];
        $this->_options['className'] = str_replace('%CLASS%', $ownerClassName, $className);

        if (isset($this->_options['tableName'])) {
            $ownerTableName = $this->_options['table']->getTableName();
            $tableName = $this->_options['tableName'];
            $this->_options['tableName'] = str_replace('%TABLE%', $ownerTableName, $tableName);
        }

        // check that class doesn't exist (otherwise we cannot create it)
        if ($this->_options['generateFiles'] === false && class_exists($this->_options['className'])) {
            $this->_table = Doctrine_Core::getTable($this->_options['className']);            
            $this->buildRelation();
            return false;
        }
        
        $this->buildTable();
        $fk = $this->buildForeignKeys($this->_options['table']);
        if(count($fk))
        {
          $this->_table->setColumns($fk);
        }

        $this->setTableDefinition();
        $this->setUp();        
        $this->buildChildDefinitions();
        $this->buildRelation();

        $this->generateClassFromTable($this->_table);

        $this->_table->initIdentifier();
    }

    /**
     * Create the new Doctrine_Table instance in $this->_table based on the owning
     * table.
     *
     * @return void
     */
    public function buildTable()
    {
        // Bind model 
        $conn = $this->_options['table']->getConnection();
        $bindConnName = $conn->getManager()->getConnectionForComponent($this->_options['table']->getComponentName())->getName();
        if ($bindConnName) {
            $conn->getManager()->bindComponent($this->_options['className'], $bindConnName);
        } else {
            $conn->getManager()->bindComponent($this->_options['className'], $conn->getName());
        }

        // Create table
        $tableClass = $conn->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);
        $this->_table = new $tableClass($this->_options['className'], $conn);        
        $this->_table->setGenerator($this);

        // If custom table name set then lets use it
        if (isset($this->_options['tableName']) && $this->_options['tableName']) {
            $this->_table->setTableName($this->_options['tableName']);
        }

        // Maintain some options from the parent table
        $options = $this->_options['table']->getOptions();

        $newOptions = array();
        $maintain = array('type', 'collate', 'charset'); // This list may need updating
        foreach ($maintain as $key) {
            if (isset($options[$key])) {
                $newOptions[$key] = $options[$key];
            }
        }

        $this->_table->setOptions($newOptions);

        $conn->addTable($this->_table);
    }

    /** 
     * Empty template method for providing the concrete plugins the ability
     * to initialize options before the actual definition is being built
     *
     * @return void
     */
    public function initOptions()
    {
        
    }

    /**
     * Build the child behavior definitions that are attached to this generator
     *
     * @return void
     */
    public function buildChildDefinitions()
    {
        if ( ! isset($this->_options['children'])) {
            throw new Doctrine_Record_Exception("Unknown option 'children'.");
        }

        foreach ($this->_options['children'] as $child) {
            if ($child instanceof Doctrine_Template) {
                if ($child->getPlugin() !== null) {
                    $this->_table->addGenerator($child->getPlugin(), get_class($child->getPlugin()));
                }

                $this->_table->addTemplate(get_class($child), $child);

                $child->setInvoker($this);
                $child->setTable($this->_table);    
                $child->setUp();             
                $child->setTableDefinition();
                   
            } else {
                $this->_table->addGenerator($child, get_class($child));
                $child->initialize($this->_table);
            }
        }
    }

    /**
     * Generates foreign keys for the plugin table based on the owner table.
     * These columns are automatically added to the generated model so we can
     * create foreign keys back to the table object that owns the plugin.
     *
     * @param Doctrine_Table $table     the table object that owns the plugin
     * @return array                    an array of foreign key definitions
     */
    public function buildForeignKeys(Doctrine_Table $table)
    {
        $fk = array();

        foreach ((array) $table->getIdentifier() as $field) {
            $def = $table->getDefinitionOf($field);

            unset($def['autoincrement']);
            unset($def['sequence']);
            unset($def['primary']);

            $col = $table->hasColumn($field) ? $field : $table->getColumnName($field) . ' as ' . $field;

            $def['primary'] = true;
            $fk[$col] = $def;
        }
        return $fk;
    }

    /**
     * Build the local relationship on the generated model for this generator 
     * instance which points to the invoking table in $this->_options['table']
     *
     * @param string $alias Alias of the foreign relation
     * @return void
     */
    public function buildLocalRelation($alias = null)
    {
        $options = array(
            'local'      => $this->getRelationLocalKey(),
            'foreign'    => $this->getRelationForeignKey(),
            'owningSide' => true
        );

        if (isset($this->_options['cascadeDelete']) && $this->_options['cascadeDelete'] && ! $this->_options['appLevelDelete']) {
            $options['onDelete'] = 'CASCADE';
            $options['onUpdate'] = 'CASCADE';
        }

        $aliasStr = '';

        if ($alias !== null) {
            $aliasStr = ' as ' . $alias;
        }

        $this->hasOne($this->_options['table']->getComponentName() . $aliasStr, $options);
    }

    /**
     * Add a Doctrine_Relation::MANY relationship to the generator owner table
     *
     * @param string $name 
     * @param array $options 
     * @return void
     */
    public function ownerHasMany($name, $options)
    {
        $this->_options['table']->hasMany($name, $options);
    }

    /**
     * Add a Doctrine_Relation::ONE relationship to the generator owner table
     *
     * @param string $name 
     * @param array $options 
     * @return void
     */
    public function ownerHasOne($name, $options)
    {
        $this->_options['table']->hasOne($name, $options);
    }

    /**
     * Build the foreign relationship on the invoking table in $this->_options['table']
     * which points back to the model generated in this generator instance.
     *
     * @param string $alias Alias of the foreign relation
     * @return void
     */
    public function buildForeignRelation($alias = null)
    {
        $options = array(
            'local'    => $this->getRelationForeignKey(),
            'foreign'  => $this->getRelationLocalKey(),
            'localKey' => false
        );

        if (isset($this->_options['cascadeDelete']) && $this->_options['cascadeDelete'] && $this->_options['appLevelDelete']) {
            $options['cascade'] = array('delete');
        }

        $aliasStr = '';

        if ($alias !== null) {
            $aliasStr = ' as ' . $alias;
        }

        $this->ownerHasMany($this->_table->getComponentName() . $aliasStr, $options);
    }

    /**
     * Get the local key of the generated relationship
     *
     * @return string $local
     */
    public function getRelationLocalKey()
    {
        return $this->getRelationForeignKey();
    }

    /**
     * Get the foreign key of the generated relationship
     *
     * @return string $foreign
     */
    public function getRelationForeignKey()
    {
        $table = $this->_options['table'];
        $identifier = $table->getIdentifier();

        foreach ((array) $identifier as $column) {
            $def = $table->getDefinitionOf($column);
            if (isset($def['primary']) && $def['primary'] && isset($def['autoincrement']) && $def['autoincrement']) {
                return $column;
            }
        }
        
        return $identifier;
    }

    /**
     * This method can be used for generating the relation from the plugin 
     * table to the owner table. By default buildForeignRelation() and buildLocalRelation() are called
     * Those methods can be overridden or this entire method can be overridden
     *
     * @return void
     */
    public function buildRelation()
    {
        $this->buildForeignRelation();
        $this->buildLocalRelation();
    }

    /**
     * Returns an array of passable options for generators
     *
     * @return array
     */
    public function getPassableOptions()
    {
        $options = $this->_options;
        unset($options['table'], $options['pluginTable'], $options['className']);
        unset($options['generateFiles'], $options['generatePath'], $options['children']);
        unset($options['builderOptions'], $options['identifier']);
        return $options;
    }
    
    /**
     * Returns definition of this table
     * 
     * @param Doctrine_Table $table
     * @return array     
     */
    public function getTableDefinition($table = null)
    {
        if(!$table) {
          $table = $this->_table;
        }
      
        $definition = array();
        $definition['columns']   = $table->getColumns();
        $definition['tableName'] = $table->getTableName();
        $definition['indexes'] = $table->getOption('indexes');
        $definition['indexBy'] = $table->getBoundQueryPart('indexBy');

        $definition['className'] = $this->_options['className'];
        $definition['toString'] = isset($this->_options['toString']) ? $this->_options['toString'] : false;

        if (isset($this->_options['listeners'])) {
            $definition['listeners'] = $this->_options['listeners'];
        }
       
        // relations
        $definition['relations'] = $table->getRelations();

        // table options
        $definition['options'] = array();
        foreach(array('type', 'collate', 'charset') as $option)
        {
          $value = $table->getOption($option);
          if(is_null($value))
          {
            continue;
          }

          $definition['options'][$option] = $value;
        }        
        
        $definition['actAs'] = array();
        
        // templates
        foreach($table->getTemplates() as $template)
        {
          $options = $template->getOriginalOptions();          
          // build actAs statement
          $definition['actAs'][get_class($template)] = count($options) ? $options : null;
        }
        
        return $definition;
    }
    
    /**
     * Generate a Doctrine_Record from a populated Doctrine_Table instance
     *
     * @param Doctrine_Table $table
     * @return void
     */
    public function generateClassFromTable(Doctrine_Table $table)
    {
        return $this->generateClass($this->getTableDefinition($table));
    }
    
    /**
     * Generates the class definition for plugin class
     *
     * @param array $definition  Definition array defining columns, relations and options
     *                           for the model
     * @return void
     */
    public function generateClass(array $definition = array())
    {
        $definition['className'] = $this->_options['className'];
        $definition['toString'] = isset($this->_options['toString']) ? $this->_options['toString'] : false;
        if (isset($this->_options['listeners'])) {
            $definition['listeners'] = $this->_options['listeners'];
        }

        $builder = new Doctrine_Import_Builder();
        $builderOptions = isset($this->_options['builderOptions']) ? (array) $this->_options['builderOptions']:array();
        $builder->setOptions($builderOptions);

        if ($this->_options['generateFiles']) {
            if (isset($this->_options['generatePath']) && $this->_options['generatePath']) {
                $builder->setTargetPath($this->_options['generatePath']);
                $builder->buildRecord($definition);
            } else {
                throw new Doctrine_Record_Exception('If you wish to generate files then you must specify the path to generate the files in.');
            }
        } else {
            $def = $builder->buildDefinition($definition);

            eval($def);
        }
    }
    
    /**
     * Fetches all generators recursively for given table
     *
     * @param Doctrine_Table $table     table object to retrieve the generators from
     * @return array                    an array of Doctrine_Record_Generator objects
     */
    public function getAllGenerators(Doctrine_Table $table)
    {
      $generators = array();

      foreach($table->getGenerators() as $name => $generator)
      {
        if($generator === null)
        {
          continue;
        }

        $generators[] = $generator;

        $generatorTable = $generator->getTable();

        if($generatorTable instanceof Doctrine_Table)
        {
          $generators = array_merge($generators, $this->getAllGenerators($generatorTable));
        }
      }

      return $generators;
    }
  
}
<?php declare(strict_types = 1);

// osfsl-C:/Users/Duong Vinh/RestaurantPOS-Laravel/vendor/composer/../laravel/framework/src/Illuminate/Database/Query/Builder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Query\Builder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-db6be736a14c119c60d355b1ba2b2eefa80d473a034b66552fe7dc2eec5308cf-8.4-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Query\\Builder',
        'filename' => 'C:/Users/Duong Vinh/RestaurantPOS-Laravel/vendor/composer/../laravel/framework/src/Illuminate/Database/Query/Builder.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Query',
    'name' => 'Illuminate\\Database\\Query\\Builder',
    'shortName' => 'Builder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 36,
    'endLine' => 4726,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Database\\Query\\Builder',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses',
      1 => 'Illuminate\\Database\\Concerns\\BuildsQueries',
      2 => 'Illuminate\\Database\\Concerns\\ExplainsQueries',
      3 => 'Illuminate\\Support\\Traits\\ForwardsCalls',
      4 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'connection',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database connection instance.
 *
 * @var \\Illuminate\\Database\\ConnectionInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'grammar' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'grammar',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database query grammar instance.
 *
 * @var \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'processor' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'processor',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database query post processor instance.
 *
 * @var \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bindings' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'bindings',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'select\' => [], \'from\' => [], \'join\' => [], \'where\' => [], \'groupBy\' => [], \'having\' => [], \'order\' => [], \'union\' => [], \'unionOrder\' => []]',
          'attributes' => 
          array (
            'startLine' => 79,
            'endLine' => 89,
            'startTokenPos' => 234,
            'startFilePos' => 2299,
            'endTokenPos' => 308,
            'endFilePos' => 2519,
          ),
        ),
        'docComment' => '/**
 * The current query value bindings.
 *
 * @var array{
 *     select: list<mixed>,
 *     from: list<mixed>,
 *     join: list<mixed>,
 *     where: list<mixed>,
 *     groupBy: list<mixed>,
 *     having: list<mixed>,
 *     order: list<mixed>,
 *     union: list<mixed>,
 *     unionOrder: list<mixed>,
 * }
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 79,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'aggregate' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'aggregate',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * An aggregate function and column to be run.
 *
 * @var array{
 *     function: string,
 *     columns: array<\\Illuminate\\Contracts\\Database\\Query\\Expression|string>
 * }|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 99,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'columns' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'columns',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The columns that should be returned.
 *
 * @var array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 106,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'distinct' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'distinct',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 115,
            'endLine' => 115,
            'startTokenPos' => 333,
            'startFilePos' => 3131,
            'endTokenPos' => 333,
            'endFilePos' => 3135,
          ),
        ),
        'docComment' => '/**
 * Indicates if the query returns distinct results.
 *
 * Occasionally contains the columns that should be distinct.
 *
 * @var bool|array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 115,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'from' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'from',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The table which the query is targeting.
 *
 * @var \\Illuminate\\Database\\Query\\Expression|string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 122,
        'endLine' => 122,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'indexHint' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'indexHint',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The index hint for the query.
 *
 * @var \\Illuminate\\Database\\Query\\IndexHint|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 129,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'joins' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'joins',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The table joins for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 136,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wheres' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'wheres',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 143,
            'endLine' => 143,
            'startTokenPos' => 365,
            'startFilePos' => 3633,
            'endTokenPos' => 366,
            'endFilePos' => 3634,
          ),
        ),
        'docComment' => '/**
 * The where constraints for the query.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 143,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'groups' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'groups',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The groupings for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 150,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'havings' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'havings',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The having constraints for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 157,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'orders' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'orders',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The orderings for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 164,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'limit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'limit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of records to return.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 171,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'groupLimit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'groupLimit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of records to return per group.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 178,
        'endLine' => 178,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'offset' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'offset',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of records to skip.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 185,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unions' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unions',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The query union statements.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 192,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unionLimit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionLimit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of union records to return.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 199,
        'endLine' => 199,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unionOffset' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionOffset',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of union records to skip.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 206,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unionOrders' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionOrders',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The orderings for the union query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 213,
        'endLine' => 213,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lock' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'lock',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Indicates whether row locking is being used.
 *
 * @var string|bool|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 220,
        'endLine' => 220,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'beforeQueryCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'beforeQueryCallbacks',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 227,
            'endLine' => 227,
            'startTokenPos' => 454,
            'startFilePos' => 5027,
            'endTokenPos' => 455,
            'endFilePos' => 5028,
          ),
        ),
        'docComment' => '/**
 * The callbacks that should be invoked before the query is executed.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 227,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'afterQueryCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'afterQueryCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 234,
            'endLine' => 234,
            'startTokenPos' => 466,
            'startFilePos' => 5195,
            'endTokenPos' => 467,
            'endFilePos' => 5196,
          ),
        ),
        'docComment' => '/**
 * The callbacks that should be invoked after retrieving data from the database.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 234,
        'endLine' => 234,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'operators' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'operators',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'=\', \'<\', \'>\', \'<=\', \'>=\', \'<>\', \'!=\', \'<=>\', \'like\', \'like binary\', \'not like\', \'ilike\', \'&\', \'|\', \'^\', \'<<\', \'>>\', \'&~\', \'is\', \'is not\', \'rlike\', \'not rlike\', \'regexp\', \'not regexp\', \'~\', \'~*\', \'!~\', \'!~*\', \'similar to\', \'not similar to\', \'not ilike\', \'~~*\', \'!~~*\']',
          'attributes' => 
          array (
            'startLine' => 241,
            'endLine' => 248,
            'startTokenPos' => 478,
            'startFilePos' => 5314,
            'endTokenPos' => 579,
            'endFilePos' => 5637,
          ),
        ),
        'docComment' => '/**
 * All of the available clause operators.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 241,
        'endLine' => 248,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bitwiseOperators' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'bitwiseOperators',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'&\', \'|\', \'^\', \'<<\', \'>>\', \'&~\']',
          'attributes' => 
          array (
            'startLine' => 255,
            'endLine' => 257,
            'startTokenPos' => 590,
            'startFilePos' => 5763,
            'endTokenPos' => 610,
            'endFilePos' => 5810,
          ),
        ),
        'docComment' => '/**
 * All of the available bitwise operators.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 255,
        'endLine' => 257,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'useWritePdo' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'useWritePdo',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 264,
            'endLine' => 264,
            'startTokenPos' => 621,
            'startFilePos' => 5928,
            'endTokenPos' => 621,
            'endFilePos' => 5932,
          ),
        ),
        'docComment' => '/**
 * Whether to use write pdo for the select.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 264,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\ConnectionInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 270,
            'endLine' => 270,
            'startColumn' => 9,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'grammar' => 
          array (
            'name' => 'grammar',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 271,
                'endLine' => 271,
                'startTokenPos' => 645,
                'startFilePos' => 6098,
                'endTokenPos' => 645,
                'endFilePos' => 6101,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Illuminate\\Database\\Query\\Grammars\\Grammar',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 9,
            'endColumn' => 32,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'processor' => 
          array (
            'name' => 'processor',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 272,
                'endLine' => 272,
                'startTokenPos' => 655,
                'startFilePos' => 6136,
                'endTokenPos' => 655,
                'endFilePos' => 6139,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Illuminate\\Database\\Query\\Processors\\Processor',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 9,
            'endColumn' => 36,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new query builder instance.
 */',
        'startLine' => 269,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'select' => 
      array (
        'name' => 'select',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 285,
                'endLine' => 285,
                'startTokenPos' => 721,
                'startFilePos' => 6493,
                'endTokenPos' => 723,
                'endFilePos' => 6497,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 285,
            'endLine' => 285,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the columns to be selected.
 *
 * @param  mixed  $columns
 * @return $this
 */',
        'startLine' => 285,
        'endLine' => 303,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectSub' => 
      array (
        'name' => 'selectSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 314,
            'endLine' => 314,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 314,
            'endLine' => 314,
            'startColumn' => 39,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a subselect expression to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 314,
        'endLine' => 321,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectExpression' => 
      array (
        'name' => 'selectExpression',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 330,
            'endLine' => 330,
            'startColumn' => 38,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 330,
            'endLine' => 330,
            'startColumn' => 51,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a select expression to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression  $expression
 * @param  string  $as
 * @return $this
 */',
        'startLine' => 330,
        'endLine' => 335,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectRaw' => 
      array (
        'name' => 'selectRaw',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 343,
            'endLine' => 343,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 343,
                'endLine' => 343,
                'startTokenPos' => 1011,
                'startFilePos' => 8154,
                'endTokenPos' => 1012,
                'endFilePos' => 8155,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 343,
            'endLine' => 343,
            'startColumn' => 44,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a new "raw" select expression to the query.
 *
 * @param  string  $expression
 * @return $this
 */',
        'startLine' => 343,
        'endLine' => 352,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'fromSub' => 
      array (
        'name' => 'fromSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 363,
            'endLine' => 363,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 363,
            'endLine' => 363,
            'startColumn' => 37,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Makes "from" fetch from a subquery.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 363,
        'endLine' => 368,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'fromRaw' => 
      array (
        'name' => 'fromRaw',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 377,
            'endLine' => 377,
            'startColumn' => 29,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 377,
                'endLine' => 377,
                'startTokenPos' => 1134,
                'startFilePos' => 9027,
                'endTokenPos' => 1135,
                'endFilePos' => 9028,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 377,
            'endLine' => 377,
            'startColumn' => 42,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "from" clause to the query.
 *
 * @param  string  $expression
 * @param  mixed  $bindings
 * @return $this
 */',
        'startLine' => 377,
        'endLine' => 384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'createSub' => 
      array (
        'name' => 'createSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 392,
            'endLine' => 392,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a subquery and parse it.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return array
 */',
        'startLine' => 392,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'parseSub' => 
      array (
        'name' => 'parseSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 414,
            'endLine' => 414,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the subquery into SQL and bindings.
 *
 * @param  mixed  $query
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 414,
        'endLine' => 427,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'prependDatabaseNameIfCrossDatabaseQuery' => 
      array (
        'name' => 'prependDatabaseNameIfCrossDatabaseQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 435,
            'endLine' => 435,
            'startColumn' => 64,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepend the database name if the given query is on another database.
 *
 * @param  mixed  $query
 * @return mixed
 */',
        'startLine' => 435,
        'endLine' => 447,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addSelect' => 
      array (
        'name' => 'addSelect',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 455,
            'endLine' => 455,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a new select column to the query.
 *
 * @param  mixed  $column
 * @return $this
 */',
        'startLine' => 455,
        'endLine' => 478,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectVectorDistance' => 
      array (
        'name' => 'selectVectorDistance',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 488,
            'endLine' => 488,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 488,
            'endLine' => 488,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 488,
                'endLine' => 488,
                'startTokenPos' => 1689,
                'startFilePos' => 12510,
                'endTokenPos' => 1689,
                'endFilePos' => 12513,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 488,
            'endLine' => 488,
            'startColumn' => 60,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a vector-similarity selection to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  string|null  $as
 * @return $this
 */',
        'startLine' => 488,
        'endLine' => 511,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'distinct' => 
      array (
        'name' => 'distinct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Force the query to only return distinct results.
 *
 * @return $this
 */',
        'startLine' => 518,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 538,
            'endLine' => 538,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 538,
                'endLine' => 538,
                'startTokenPos' => 1945,
                'startFilePos' => 13884,
                'endTokenPos' => 1945,
                'endFilePos' => 13887,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 538,
            'endLine' => 538,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the table which the query is targeting.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  string|null  $as
 * @return $this
 */',
        'startLine' => 538,
        'endLine' => 547,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'useIndex' => 
      array (
        'name' => 'useIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an index hint to suggest a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 555,
        'endLine' => 560,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forceIndex' => 
      array (
        'name' => 'forceIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 568,
            'endLine' => 568,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an index hint to force a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 568,
        'endLine' => 573,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ignoreIndex' => 
      array (
        'name' => 'ignoreIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 581,
            'endLine' => 581,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an index hint to ignore a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 581,
        'endLine' => 586,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'join' => 
      array (
        'name' => 'join',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 599,
                'endLine' => 599,
                'startTokenPos' => 2139,
                'startFilePos' => 15340,
                'endTokenPos' => 2139,
                'endFilePos' => 15343,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 599,
                'endLine' => 599,
                'startTokenPos' => 2146,
                'startFilePos' => 15356,
                'endTokenPos' => 2146,
                'endFilePos' => 15359,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 60,
            'endColumn' => 73,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 599,
                'endLine' => 599,
                'startTokenPos' => 2153,
                'startFilePos' => 15370,
                'endTokenPos' => 2153,
                'endFilePos' => 15376,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 76,
            'endColumn' => 90,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'where' => 
          array (
            'name' => 'where',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 599,
                'endLine' => 599,
                'startTokenPos' => 2160,
                'startFilePos' => 15388,
                'endTokenPos' => 2160,
                'endFilePos' => 15392,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 93,
            'endColumn' => 106,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "join" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @param  string  $type
 * @param  bool  $where
 * @return $this
 */',
        'startLine' => 599,
        'endLine' => 626,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinWhere' => 
      array (
        'name' => 'joinWhere',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 47,
            'endColumn' => 55,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 58,
            'endColumn' => 64,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 638,
                'endLine' => 638,
                'startTokenPos' => 2330,
                'startFilePos' => 16858,
                'endTokenPos' => 2330,
                'endFilePos' => 16864,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 67,
            'endColumn' => 81,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $second
 * @param  string  $type
 * @return $this
 */',
        'startLine' => 638,
        'endLine' => 641,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinSub' => 
      array (
        'name' => 'joinSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 37,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 657,
                'endLine' => 657,
                'startTokenPos' => 2383,
                'startFilePos' => 17568,
                'endTokenPos' => 2383,
                'endFilePos' => 17571,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 657,
                'endLine' => 657,
                'startTokenPos' => 2390,
                'startFilePos' => 17584,
                'endTokenPos' => 2390,
                'endFilePos' => 17587,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 68,
            'endColumn' => 81,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 657,
                'endLine' => 657,
                'startTokenPos' => 2397,
                'startFilePos' => 17598,
                'endTokenPos' => 2397,
                'endFilePos' => 17604,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 84,
            'endColumn' => 98,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'where' => 
          array (
            'name' => 'where',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 657,
                'endLine' => 657,
                'startTokenPos' => 2404,
                'startFilePos' => 17616,
                'endTokenPos' => 2404,
                'endFilePos' => 17620,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 657,
            'endLine' => 657,
            'startColumn' => 101,
            'endColumn' => 114,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "subquery join" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @param  string  $type
 * @param  bool  $where
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 657,
        'endLine' => 666,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinLateral' => 
      array (
        'name' => 'joinLateral',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 674,
            'endLine' => 674,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 674,
            'endLine' => 674,
            'startColumn' => 41,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 674,
                'endLine' => 674,
                'startTokenPos' => 2511,
                'startFilePos' => 18189,
                'endTokenPos' => 2511,
                'endFilePos' => 18195,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 674,
            'endLine' => 674,
            'startColumn' => 53,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "lateral join" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return $this
 */',
        'startLine' => 674,
        'endLine' => 685,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinLateral' => 
      array (
        'name' => 'leftJoinLateral',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 693,
            'endLine' => 693,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 693,
            'endLine' => 693,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a lateral left join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return $this
 */',
        'startLine' => 693,
        'endLine' => 696,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoin' => 
      array (
        'name' => 'leftJoin',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 707,
            'endLine' => 707,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 707,
            'endLine' => 707,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 707,
                'endLine' => 707,
                'startTokenPos' => 2652,
                'startFilePos' => 19263,
                'endTokenPos' => 2652,
                'endFilePos' => 19266,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 707,
            'endLine' => 707,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 707,
                'endLine' => 707,
                'startTokenPos' => 2659,
                'startFilePos' => 19279,
                'endTokenPos' => 2659,
                'endFilePos' => 19282,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 707,
            'endLine' => 707,
            'startColumn' => 64,
            'endColumn' => 77,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a left join to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 707,
        'endLine' => 710,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinWhere' => 
      array (
        'name' => 'leftJoinWhere',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 51,
            'endColumn' => 59,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 62,
            'endColumn' => 68,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 721,
        'endLine' => 724,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinSub' => 
      array (
        'name' => 'leftJoinSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 41,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 736,
                'endLine' => 736,
                'startTokenPos' => 2755,
                'startFilePos' => 20409,
                'endTokenPos' => 2755,
                'endFilePos' => 20412,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 736,
                'endLine' => 736,
                'startTokenPos' => 2762,
                'startFilePos' => 20425,
                'endTokenPos' => 2762,
                'endFilePos' => 20428,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 72,
            'endColumn' => 85,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a subquery left join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 736,
        'endLine' => 739,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoin' => 
      array (
        'name' => 'rightJoin',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 750,
            'endLine' => 750,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 750,
            'endLine' => 750,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 750,
                'endLine' => 750,
                'startTokenPos' => 2812,
                'startFilePos' => 20903,
                'endTokenPos' => 2812,
                'endFilePos' => 20906,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 750,
            'endLine' => 750,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 750,
                'endLine' => 750,
                'startTokenPos' => 2819,
                'startFilePos' => 20919,
                'endTokenPos' => 2819,
                'endFilePos' => 20922,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 750,
            'endLine' => 750,
            'startColumn' => 65,
            'endColumn' => 78,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a right join to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 750,
        'endLine' => 753,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoinWhere' => 
      array (
        'name' => 'rightJoinWhere',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 764,
            'endLine' => 764,
            'startColumn' => 36,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 764,
            'endLine' => 764,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 764,
            'endLine' => 764,
            'startColumn' => 52,
            'endColumn' => 60,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 764,
            'endLine' => 764,
            'startColumn' => 63,
            'endColumn' => 69,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "right join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $second
 * @return $this
 */',
        'startLine' => 764,
        'endLine' => 767,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoinSub' => 
      array (
        'name' => 'rightJoinSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 779,
            'endLine' => 779,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 779,
            'endLine' => 779,
            'startColumn' => 42,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 779,
            'endLine' => 779,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 779,
                'endLine' => 779,
                'startTokenPos' => 2915,
                'startFilePos' => 22055,
                'endTokenPos' => 2915,
                'endFilePos' => 22058,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 779,
            'endLine' => 779,
            'startColumn' => 55,
            'endColumn' => 70,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 779,
                'endLine' => 779,
                'startTokenPos' => 2922,
                'startFilePos' => 22071,
                'endTokenPos' => 2922,
                'endFilePos' => 22074,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 779,
            'endLine' => 779,
            'startColumn' => 73,
            'endColumn' => 86,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a subquery right join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 779,
        'endLine' => 782,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'crossJoin' => 
      array (
        'name' => 'crossJoin',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 793,
            'endLine' => 793,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 793,
                'endLine' => 793,
                'startTokenPos' => 2969,
                'startFilePos' => 22601,
                'endTokenPos' => 2969,
                'endFilePos' => 22604,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 793,
            'endLine' => 793,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 793,
                'endLine' => 793,
                'startTokenPos' => 2976,
                'startFilePos' => 22619,
                'endTokenPos' => 2976,
                'endFilePos' => 22622,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 793,
            'endLine' => 793,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 793,
                'endLine' => 793,
                'startTokenPos' => 2983,
                'startFilePos' => 22635,
                'endTokenPos' => 2983,
                'endFilePos' => 22638,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 793,
            'endLine' => 793,
            'startColumn' => 72,
            'endColumn' => 85,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "cross join" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 793,
        'endLine' => 802,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'crossJoinSub' => 
      array (
        'name' => 'crossJoinSub',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 811,
            'endLine' => 811,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 811,
            'endLine' => 811,
            'startColumn' => 42,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a subquery cross join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 */',
        'startLine' => 811,
        'endLine' => 822,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'newJoinClause' => 
      array (
        'name' => 'newJoinClause',
        'parameters' => 
        array (
          'parentQuery' => 
          array (
            'name' => 'parentQuery',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 831,
            'endLine' => 831,
            'startColumn' => 38,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 831,
            'endLine' => 831,
            'startColumn' => 57,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 831,
            'endLine' => 831,
            'startColumn' => 64,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new "join" clause.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @return \\Illuminate\\Database\\Query\\JoinClause
 */',
        'startLine' => 831,
        'endLine' => 834,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'newJoinLateralClause' => 
      array (
        'name' => 'newJoinLateralClause',
        'parameters' => 
        array (
          'parentQuery' => 
          array (
            'name' => 'parentQuery',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 843,
            'endLine' => 843,
            'startColumn' => 45,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 843,
            'endLine' => 843,
            'startColumn' => 64,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 843,
            'endLine' => 843,
            'startColumn' => 71,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new "join lateral" clause.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @return \\Illuminate\\Database\\Query\\JoinLateralClause
 */',
        'startLine' => 843,
        'endLine' => 846,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'mergeWheres' => 
      array (
        'name' => 'mergeWheres',
        'parameters' => 
        array (
          'wheres' => 
          array (
            'name' => 'wheres',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 855,
            'endLine' => 855,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 855,
            'endLine' => 855,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Merge an array of "where" clauses and bindings.
 *
 * @param  array  $wheres
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 855,
        'endLine' => 864,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'where' => 
      array (
        'name' => 'where',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 875,
                'endLine' => 875,
                'startTokenPos' => 3312,
                'startFilePos' => 24964,
                'endTokenPos' => 3312,
                'endFilePos' => 24967,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 875,
                'endLine' => 875,
                'startTokenPos' => 3319,
                'startFilePos' => 24979,
                'endTokenPos' => 3319,
                'endFilePos' => 24982,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 54,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 875,
                'endLine' => 875,
                'startTokenPos' => 3326,
                'startFilePos' => 24996,
                'endTokenPos' => 3326,
                'endFilePos' => 25000,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 69,
            'endColumn' => 84,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a basic "where" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 875,
        'endLine' => 970,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addArrayOfWheres' => 
      array (
        'name' => 'addArrayOfWheres',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 980,
            'endLine' => 980,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 980,
            'endLine' => 980,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 980,
                'endLine' => 980,
                'startTokenPos' => 3916,
                'startFilePos' => 29481,
                'endTokenPos' => 3916,
                'endFilePos' => 29487,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 980,
            'endLine' => 980,
            'startColumn' => 60,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an array of "where" clauses to the query.
 *
 * @param  array  $column
 * @param  string  $boolean
 * @param  string  $method
 * @return $this
 */',
        'startLine' => 980,
        'endLine' => 991,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'prepareValueAndOperator' => 
      array (
        'name' => 'prepareValueAndOperator',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1003,
            'endLine' => 1003,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1003,
            'endLine' => 1003,
            'startColumn' => 53,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'useDefault' => 
          array (
            'name' => 'useDefault',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1003,
                'endLine' => 1003,
                'startTokenPos' => 4057,
                'startFilePos' => 30253,
                'endTokenPos' => 4057,
                'endFilePos' => 30257,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1003,
            'endLine' => 1003,
            'startColumn' => 64,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the value and operator for a where clause.
 *
 * @param  string  $value
 * @param  string  $operator
 * @param  bool  $useDefault
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 1003,
        'endLine' => 1012,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'invalidOperatorAndValue' => 
      array (
        'name' => 'invalidOperatorAndValue',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1023,
            'endLine' => 1023,
            'startColumn' => 48,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1023,
            'endLine' => 1023,
            'startColumn' => 59,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given operator and value combination is legal.
 *
 * Prevents using Null values with invalid operators.
 *
 * @param  string  $operator
 * @param  mixed  $value
 * @return bool
 */',
        'startLine' => 1023,
        'endLine' => 1027,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'invalidOperator' => 
      array (
        'name' => 'invalidOperator',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1035,
            'endLine' => 1035,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given operator is supported.
 *
 * @param  string  $operator
 * @return bool
 */',
        'startLine' => 1035,
        'endLine' => 1039,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'isBitwiseOperator' => 
      array (
        'name' => 'isBitwiseOperator',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1047,
            'endLine' => 1047,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the operator is a bitwise operator.
 *
 * @param  string  $operator
 * @return bool
 */',
        'startLine' => 1047,
        'endLine' => 1051,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhere' => 
      array (
        'name' => 'orWhere',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1061,
            'endLine' => 1061,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1061,
                'endLine' => 1061,
                'startTokenPos' => 4325,
                'startFilePos' => 32052,
                'endTokenPos' => 4325,
                'endFilePos' => 32055,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1061,
            'endLine' => 1061,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1061,
                'endLine' => 1061,
                'startTokenPos' => 4332,
                'startFilePos' => 32067,
                'endTokenPos' => 4332,
                'endFilePos' => 32070,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1061,
            'endLine' => 1061,
            'startColumn' => 56,
            'endColumn' => 68,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1061,
        'endLine' => 1068,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNot' => 
      array (
        'name' => 'whereNot',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1079,
            'endLine' => 1079,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1079,
                'endLine' => 1079,
                'startTokenPos' => 4404,
                'startFilePos' => 32609,
                'endTokenPos' => 4404,
                'endFilePos' => 32612,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1079,
            'endLine' => 1079,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1079,
                'endLine' => 1079,
                'startTokenPos' => 4411,
                'startFilePos' => 32624,
                'endTokenPos' => 4411,
                'endFilePos' => 32627,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1079,
            'endLine' => 1079,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1079,
                'endLine' => 1079,
                'startTokenPos' => 4418,
                'startFilePos' => 32641,
                'endTokenPos' => 4418,
                'endFilePos' => 32645,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1079,
            'endLine' => 1079,
            'startColumn' => 72,
            'endColumn' => 87,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a basic "where not" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1079,
        'endLine' => 1088,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNot' => 
      array (
        'name' => 'orWhereNot',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1098,
            'endLine' => 1098,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1098,
                'endLine' => 1098,
                'startTokenPos' => 4529,
                'startFilePos' => 33283,
                'endTokenPos' => 4529,
                'endFilePos' => 33286,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1098,
            'endLine' => 1098,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1098,
                'endLine' => 1098,
                'startTokenPos' => 4536,
                'startFilePos' => 33298,
                'endTokenPos' => 4536,
                'endFilePos' => 33301,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1098,
            'endLine' => 1098,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1098,
        'endLine' => 1101,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereColumn' => 
      array (
        'name' => 'whereColumn',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1112,
            'endLine' => 1112,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1112,
                'endLine' => 1112,
                'startTokenPos' => 4577,
                'startFilePos' => 33738,
                'endTokenPos' => 4577,
                'endFilePos' => 33741,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1112,
            'endLine' => 1112,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1112,
                'endLine' => 1112,
                'startTokenPos' => 4584,
                'startFilePos' => 33754,
                'endTokenPos' => 4584,
                'endFilePos' => 33757,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1112,
            'endLine' => 1112,
            'startColumn' => 59,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1112,
                'endLine' => 1112,
                'startTokenPos' => 4591,
                'startFilePos' => 33771,
                'endTokenPos' => 4591,
                'endFilePos' => 33775,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1112,
            'endLine' => 1112,
            'startColumn' => 75,
            'endColumn' => 90,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where" clause comparing two columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|array  $first
 * @param  string|null  $operator
 * @param  string|null  $second
 * @param  string|null  $boolean
 * @return $this
 */',
        'startLine' => 1112,
        'endLine' => 1138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereColumn' => 
      array (
        'name' => 'orWhereColumn',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1148,
            'endLine' => 1148,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1148,
                'endLine' => 1148,
                'startTokenPos' => 4732,
                'startFilePos' => 35249,
                'endTokenPos' => 4732,
                'endFilePos' => 35252,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1148,
            'endLine' => 1148,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1148,
                'endLine' => 1148,
                'startTokenPos' => 4739,
                'startFilePos' => 35265,
                'endTokenPos' => 4739,
                'endFilePos' => 35268,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1148,
            'endLine' => 1148,
            'startColumn' => 61,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where" clause comparing two columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|array  $first
 * @param  string|null  $operator
 * @param  string|null  $second
 * @return $this
 */',
        'startLine' => 1148,
        'endLine' => 1151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereVectorSimilarTo' => 
      array (
        'name' => 'whereVectorSimilarTo',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1162,
            'endLine' => 1162,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1162,
            'endLine' => 1162,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'minSimilarity' => 
          array (
            'name' => 'minSimilarity',
            'default' => 
            array (
              'code' => '0.6',
              'attributes' => 
              array (
                'startLine' => 1162,
                'endLine' => 1162,
                'startTokenPos' => 4783,
                'startFilePos' => 35915,
                'endTokenPos' => 4783,
                'endFilePos' => 35917,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1162,
            'endLine' => 1162,
            'startColumn' => 60,
            'endColumn' => 79,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'order' => 
          array (
            'name' => 'order',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1162,
                'endLine' => 1162,
                'startTokenPos' => 4790,
                'startFilePos' => 35929,
                'endTokenPos' => 4790,
                'endFilePos' => 35932,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1162,
            'endLine' => 1162,
            'startColumn' => 82,
            'endColumn' => 94,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a vector similarity clause to the query, filtering by minimum similarity and ordering by similarity.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $minSimilarity  A value between 0.0 and 1.0, where 1.0 is identical.
 * @param  bool  $order
 * @return $this
 */',
        'startLine' => 1162,
        'endLine' => 1175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereVectorDistanceLessThan' => 
      array (
        'name' => 'whereVectorDistanceLessThan',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1186,
            'endLine' => 1186,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1186,
            'endLine' => 1186,
            'startColumn' => 58,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'maxDistance' => 
          array (
            'name' => 'maxDistance',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1186,
            'endLine' => 1186,
            'startColumn' => 67,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1186,
                'endLine' => 1186,
                'startTokenPos' => 4895,
                'startFilePos' => 36729,
                'endTokenPos' => 4895,
                'endFilePos' => 36733,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1186,
            'endLine' => 1186,
            'startColumn' => 81,
            'endColumn' => 96,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a vector distance "where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $maxDistance
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1186,
        'endLine' => 1207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereVectorDistanceLessThan' => 
      array (
        'name' => 'orWhereVectorDistanceLessThan',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1217,
            'endLine' => 1217,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1217,
            'endLine' => 1217,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'maxDistance' => 
          array (
            'name' => 'maxDistance',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1217,
            'endLine' => 1217,
            'startColumn' => 69,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a vector distance "or where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $maxDistance
 * @return $this
 */',
        'startLine' => 1217,
        'endLine' => 1220,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereRaw' => 
      array (
        'name' => 'whereRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 30,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1230,
                'endLine' => 1230,
                'startTokenPos' => 5065,
                'startFilePos' => 38136,
                'endTokenPos' => 5066,
                'endFilePos' => 38137,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1230,
                'endLine' => 1230,
                'startTokenPos' => 5073,
                'startFilePos' => 38151,
                'endTokenPos' => 5073,
                'endFilePos' => 38155,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 52,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $sql
 * @param  mixed  $bindings
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1230,
        'endLine' => 1237,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereRaw' => 
      array (
        'name' => 'orWhereRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1246,
            'endLine' => 1246,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1246,
                'endLine' => 1246,
                'startTokenPos' => 5144,
                'startFilePos' => 38535,
                'endTokenPos' => 5145,
                'endFilePos' => 38536,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1246,
            'endLine' => 1246,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "or where" clause to the query.
 *
 * @param  string  $sql
 * @param  mixed  $bindings
 * @return $this
 */',
        'startLine' => 1246,
        'endLine' => 1249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereLike' => 
      array (
        'name' => 'whereLike',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1261,
                'endLine' => 1261,
                'startTokenPos' => 5186,
                'startFilePos' => 38965,
                'endTokenPos' => 5186,
                'endFilePos' => 38969,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 48,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1261,
                'endLine' => 1261,
                'startTokenPos' => 5193,
                'startFilePos' => 38983,
                'endTokenPos' => 5193,
                'endFilePos' => 38987,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 72,
            'endColumn' => 87,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1261,
                'endLine' => 1261,
                'startTokenPos' => 5200,
                'startFilePos' => 38997,
                'endTokenPos' => 5200,
                'endFilePos' => 39001,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 90,
            'endColumn' => 101,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1261,
        'endLine' => 1274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereLike' => 
      array (
        'name' => 'orWhereLike',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1284,
            'endLine' => 1284,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1284,
            'endLine' => 1284,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1284,
                'endLine' => 1284,
                'startTokenPos' => 5309,
                'startFilePos' => 39673,
                'endTokenPos' => 5309,
                'endFilePos' => 39677,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1284,
            'endLine' => 1284,
            'startColumn' => 50,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @return $this
 */',
        'startLine' => 1284,
        'endLine' => 1287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotLike' => 
      array (
        'name' => 'whereNotLike',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1298,
                'endLine' => 1298,
                'startTokenPos' => 5356,
                'startFilePos' => 40111,
                'endTokenPos' => 5356,
                'endFilePos' => 40115,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 51,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1298,
                'endLine' => 1298,
                'startTokenPos' => 5363,
                'startFilePos' => 40129,
                'endTokenPos' => 5363,
                'endFilePos' => 40133,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1298,
            'endLine' => 1298,
            'startColumn' => 75,
            'endColumn' => 90,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1298,
        'endLine' => 1301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotLike' => 
      array (
        'name' => 'orWhereNotLike',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1311,
            'endLine' => 1311,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1311,
            'endLine' => 1311,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1311,
                'endLine' => 1311,
                'startTokenPos' => 5410,
                'startFilePos' => 40544,
                'endTokenPos' => 5410,
                'endFilePos' => 40548,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1311,
            'endLine' => 1311,
            'startColumn' => 53,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @return $this
 */',
        'startLine' => 1311,
        'endLine' => 1314,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIn' => 
      array (
        'name' => 'whereIn',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1325,
            'endLine' => 1325,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1325,
            'endLine' => 1325,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1325,
                'endLine' => 1325,
                'startTokenPos' => 5454,
                'startFilePos' => 40952,
                'endTokenPos' => 5454,
                'endFilePos' => 40956,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1325,
            'endLine' => 1325,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1325,
                'endLine' => 1325,
                'startTokenPos' => 5461,
                'startFilePos' => 40966,
                'endTokenPos' => 5461,
                'endFilePos' => 40970,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1325,
            'endLine' => 1325,
            'startColumn' => 65,
            'endColumn' => 76,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1325,
        'endLine' => 1359,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIn' => 
      array (
        'name' => 'orWhereIn',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1368,
            'endLine' => 1368,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1368,
            'endLine' => 1368,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @return $this
 */',
        'startLine' => 1368,
        'endLine' => 1371,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotIn' => 
      array (
        'name' => 'whereNotIn',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1381,
            'endLine' => 1381,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1381,
            'endLine' => 1381,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1381,
                'endLine' => 1381,
                'startTokenPos' => 5717,
                'startFilePos' => 43081,
                'endTokenPos' => 5717,
                'endFilePos' => 43085,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1381,
            'endLine' => 1381,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1381,
        'endLine' => 1384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotIn' => 
      array (
        'name' => 'orWhereNotIn',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1393,
            'endLine' => 1393,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1393,
            'endLine' => 1393,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @return $this
 */',
        'startLine' => 1393,
        'endLine' => 1396,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIntegerInRaw' => 
      array (
        'name' => 'whereIntegerInRaw',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 48,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1407,
                'endLine' => 1407,
                'startTokenPos' => 5795,
                'startFilePos' => 43832,
                'endTokenPos' => 5795,
                'endFilePos' => 43836,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 57,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1407,
                'endLine' => 1407,
                'startTokenPos' => 5802,
                'startFilePos' => 43846,
                'endTokenPos' => 5802,
                'endFilePos' => 43850,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 75,
            'endColumn' => 86,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1407,
        'endLine' => 1424,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIntegerInRaw' => 
      array (
        'name' => 'orWhereIntegerInRaw',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1433,
            'endLine' => 1433,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1433,
            'endLine' => 1433,
            'startColumn' => 50,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @return $this
 */',
        'startLine' => 1433,
        'endLine' => 1436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIntegerNotInRaw' => 
      array (
        'name' => 'whereIntegerNotInRaw',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1446,
            'endLine' => 1446,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1446,
            'endLine' => 1446,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1446,
                'endLine' => 1446,
                'startTokenPos' => 5981,
                'startFilePos' => 44954,
                'endTokenPos' => 5981,
                'endFilePos' => 44958,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1446,
            'endLine' => 1446,
            'startColumn' => 60,
            'endColumn' => 75,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1446,
        'endLine' => 1449,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIntegerNotInRaw' => 
      array (
        'name' => 'orWhereIntegerNotInRaw',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1458,
            'endLine' => 1458,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1458,
            'endLine' => 1458,
            'startColumn' => 53,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @return $this
 */',
        'startLine' => 1458,
        'endLine' => 1461,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNull' => 
      array (
        'name' => 'whereNull',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1471,
            'endLine' => 1471,
            'startColumn' => 31,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1471,
                'endLine' => 1471,
                'startTokenPos' => 6056,
                'startFilePos' => 45698,
                'endTokenPos' => 6056,
                'endFilePos' => 45702,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1471,
            'endLine' => 1471,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1471,
                'endLine' => 1471,
                'startTokenPos' => 6063,
                'startFilePos' => 45712,
                'endTokenPos' => 6063,
                'endFilePos' => 45716,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1471,
            'endLine' => 1471,
            'startColumn' => 59,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1471,
        'endLine' => 1480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNull' => 
      array (
        'name' => 'orWhereNull',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1488,
            'endLine' => 1488,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @return $this
 */',
        'startLine' => 1488,
        'endLine' => 1491,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotNull' => 
      array (
        'name' => 'whereNotNull',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1500,
            'endLine' => 1500,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1500,
                'endLine' => 1500,
                'startTokenPos' => 6172,
                'startFilePos' => 46477,
                'endTokenPos' => 6172,
                'endFilePos' => 46481,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1500,
            'endLine' => 1500,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1500,
        'endLine' => 1503,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereBetween' => 
      array (
        'name' => 'whereBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1513,
            'endLine' => 1513,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1513,
            'endLine' => 1513,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1513,
                'endLine' => 1513,
                'startTokenPos' => 6215,
                'startFilePos' => 46937,
                'endTokenPos' => 6215,
                'endFilePos' => 46941,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1513,
            'endLine' => 1513,
            'startColumn' => 61,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1513,
                'endLine' => 1513,
                'startTokenPos' => 6222,
                'startFilePos' => 46951,
                'endTokenPos' => 6222,
                'endFilePos' => 46955,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1513,
            'endLine' => 1513,
            'startColumn' => 79,
            'endColumn' => 90,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1513,
        'endLine' => 1533,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereBetweenColumns' => 
      array (
        'name' => 'whereBetweenColumns',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 50,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1543,
                'endLine' => 1543,
                'startTokenPos' => 6420,
                'startFilePos' => 47915,
                'endTokenPos' => 6420,
                'endFilePos' => 47919,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 65,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1543,
                'endLine' => 1543,
                'startTokenPos' => 6427,
                'startFilePos' => 47929,
                'endTokenPos' => 6427,
                'endFilePos' => 47933,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 83,
            'endColumn' => 94,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1543,
        'endLine' => 1550,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereBetween' => 
      array (
        'name' => 'orWhereBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1558,
            'endLine' => 1558,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1558,
            'endLine' => 1558,
            'startColumn' => 45,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1558,
        'endLine' => 1561,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereBetweenColumns' => 
      array (
        'name' => 'orWhereBetweenColumns',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1569,
            'endLine' => 1569,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1569,
            'endLine' => 1569,
            'startColumn' => 52,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1569,
        'endLine' => 1572,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotBetween' => 
      array (
        'name' => 'whereNotBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1581,
            'endLine' => 1581,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1581,
            'endLine' => 1581,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1581,
                'endLine' => 1581,
                'startTokenPos' => 6564,
                'startFilePos' => 49181,
                'endTokenPos' => 6564,
                'endFilePos' => 49185,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1581,
            'endLine' => 1581,
            'startColumn' => 64,
            'endColumn' => 79,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1581,
        'endLine' => 1584,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotBetweenColumns' => 
      array (
        'name' => 'whereNotBetweenColumns',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1593,
            'endLine' => 1593,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1593,
            'endLine' => 1593,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1593,
                'endLine' => 1593,
                'startTokenPos' => 6610,
                'startFilePos' => 49575,
                'endTokenPos' => 6610,
                'endFilePos' => 49579,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1593,
            'endLine' => 1593,
            'startColumn' => 68,
            'endColumn' => 83,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1593,
        'endLine' => 1596,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotBetween' => 
      array (
        'name' => 'orWhereNotBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1604,
            'endLine' => 1604,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1604,
            'endLine' => 1604,
            'startColumn' => 48,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1604,
        'endLine' => 1607,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotBetweenColumns' => 
      array (
        'name' => 'orWhereNotBetweenColumns',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1615,
            'endLine' => 1615,
            'startColumn' => 46,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1615,
            'endLine' => 1615,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1615,
        'endLine' => 1618,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereValueBetween' => 
      array (
        'name' => 'whereValueBetween',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1629,
            'endLine' => 1629,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1629,
            'endLine' => 1629,
            'startColumn' => 47,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1629,
                'endLine' => 1629,
                'startTokenPos' => 6728,
                'startFilePos' => 50845,
                'endTokenPos' => 6728,
                'endFilePos' => 50849,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1629,
            'endLine' => 1629,
            'startColumn' => 63,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1629,
                'endLine' => 1629,
                'startTokenPos' => 6735,
                'startFilePos' => 50859,
                'endTokenPos' => 6735,
                'endFilePos' => 50863,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1629,
            'endLine' => 1629,
            'startColumn' => 81,
            'endColumn' => 92,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1629,
        'endLine' => 1638,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereValueBetween' => 
      array (
        'name' => 'orWhereValueBetween',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1647,
            'endLine' => 1647,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1647,
            'endLine' => 1647,
            'startColumn' => 49,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @return $this
 */',
        'startLine' => 1647,
        'endLine' => 1650,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereValueNotBetween' => 
      array (
        'name' => 'whereValueNotBetween',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1660,
            'endLine' => 1660,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1660,
            'endLine' => 1660,
            'startColumn' => 50,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1660,
                'endLine' => 1660,
                'startTokenPos' => 6847,
                'startFilePos' => 51901,
                'endTokenPos' => 6847,
                'endFilePos' => 51905,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1660,
            'endLine' => 1660,
            'startColumn' => 66,
            'endColumn' => 81,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1660,
        'endLine' => 1663,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereValueNotBetween' => 
      array (
        'name' => 'orWhereValueNotBetween',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1672,
            'endLine' => 1672,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1672,
            'endLine' => 1672,
            'startColumn' => 52,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @return $this
 */',
        'startLine' => 1672,
        'endLine' => 1675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotNull' => 
      array (
        'name' => 'orWhereNotNull',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1683,
            'endLine' => 1683,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not null" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1683,
        'endLine' => 1686,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereDate' => 
      array (
        'name' => 'whereDate',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1697,
                'endLine' => 1697,
                'startTokenPos' => 6955,
                'startFilePos' => 53104,
                'endTokenPos' => 6955,
                'endFilePos' => 53107,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1697,
                'endLine' => 1697,
                'startTokenPos' => 6962,
                'startFilePos' => 53121,
                'endTokenPos' => 6962,
                'endFilePos' => 53125,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 66,
            'endColumn' => 81,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where date" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1697,
        'endLine' => 1717,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereDate' => 
      array (
        'name' => 'orWhereDate',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1727,
            'endLine' => 1727,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1727,
            'endLine' => 1727,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1727,
                'endLine' => 1727,
                'startTokenPos' => 7116,
                'startFilePos' => 54215,
                'endTokenPos' => 7116,
                'endFilePos' => 54218,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1727,
            'endLine' => 1727,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where date" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @return $this
 */',
        'startLine' => 1727,
        'endLine' => 1734,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereTime' => 
      array (
        'name' => 'whereTime',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1745,
            'endLine' => 1745,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1745,
            'endLine' => 1745,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1745,
                'endLine' => 1745,
                'startTokenPos' => 7191,
                'startFilePos' => 54803,
                'endTokenPos' => 7191,
                'endFilePos' => 54806,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1745,
            'endLine' => 1745,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1745,
                'endLine' => 1745,
                'startTokenPos' => 7198,
                'startFilePos' => 54820,
                'endTokenPos' => 7198,
                'endFilePos' => 54824,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1745,
            'endLine' => 1745,
            'startColumn' => 66,
            'endColumn' => 81,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where time" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1745,
        'endLine' => 1765,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereTime' => 
      array (
        'name' => 'orWhereTime',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1775,
            'endLine' => 1775,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1775,
            'endLine' => 1775,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1775,
                'endLine' => 1775,
                'startTokenPos' => 7352,
                'startFilePos' => 55914,
                'endTokenPos' => 7352,
                'endFilePos' => 55917,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1775,
            'endLine' => 1775,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where time" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @return $this
 */',
        'startLine' => 1775,
        'endLine' => 1782,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereDay' => 
      array (
        'name' => 'whereDay',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1793,
            'endLine' => 1793,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1793,
            'endLine' => 1793,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1793,
                'endLine' => 1793,
                'startTokenPos' => 7427,
                'startFilePos' => 56508,
                'endTokenPos' => 7427,
                'endFilePos' => 56511,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1793,
            'endLine' => 1793,
            'startColumn' => 50,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1793,
                'endLine' => 1793,
                'startTokenPos' => 7434,
                'startFilePos' => 56525,
                'endTokenPos' => 7434,
                'endFilePos' => 56529,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1793,
            'endLine' => 1793,
            'startColumn' => 65,
            'endColumn' => 80,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where day" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1793,
        'endLine' => 1817,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereDay' => 
      array (
        'name' => 'orWhereDay',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1827,
            'endLine' => 1827,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1827,
            'endLine' => 1827,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1827,
                'endLine' => 1827,
                'startTokenPos' => 7617,
                'startFilePos' => 57731,
                'endTokenPos' => 7617,
                'endFilePos' => 57734,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1827,
            'endLine' => 1827,
            'startColumn' => 52,
            'endColumn' => 64,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where day" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 1827,
        'endLine' => 1834,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereMonth' => 
      array (
        'name' => 'whereMonth',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1845,
            'endLine' => 1845,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1845,
            'endLine' => 1845,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1845,
                'endLine' => 1845,
                'startTokenPos' => 7692,
                'startFilePos' => 58328,
                'endTokenPos' => 7692,
                'endFilePos' => 58331,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1845,
            'endLine' => 1845,
            'startColumn' => 52,
            'endColumn' => 64,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1845,
                'endLine' => 1845,
                'startTokenPos' => 7699,
                'startFilePos' => 58345,
                'endTokenPos' => 7699,
                'endFilePos' => 58349,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1845,
            'endLine' => 1845,
            'startColumn' => 67,
            'endColumn' => 82,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where month" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1845,
        'endLine' => 1869,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereMonth' => 
      array (
        'name' => 'orWhereMonth',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 43,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1879,
                'endLine' => 1879,
                'startTokenPos' => 7882,
                'startFilePos' => 59557,
                'endTokenPos' => 7882,
                'endFilePos' => 59560,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 54,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where month" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 1879,
        'endLine' => 1886,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereYear' => 
      array (
        'name' => 'whereYear',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1897,
                'endLine' => 1897,
                'startTokenPos' => 7957,
                'startFilePos' => 60154,
                'endTokenPos' => 7957,
                'endFilePos' => 60157,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1897,
                'endLine' => 1897,
                'startTokenPos' => 7964,
                'startFilePos' => 60171,
                'endTokenPos' => 7964,
                'endFilePos' => 60175,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 66,
            'endColumn' => 81,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where year" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1897,
        'endLine' => 1917,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereYear' => 
      array (
        'name' => 'orWhereYear',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1927,
            'endLine' => 1927,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1927,
            'endLine' => 1927,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1927,
                'endLine' => 1927,
                'startTokenPos' => 8118,
                'startFilePos' => 61269,
                'endTokenPos' => 8118,
                'endFilePos' => 61272,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1927,
            'endLine' => 1927,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where year" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 1927,
        'endLine' => 1934,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addDateBasedWhere' => 
      array (
        'name' => 'addDateBasedWhere',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1946,
            'endLine' => 1946,
            'startColumn' => 42,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1946,
            'endLine' => 1946,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1946,
            'endLine' => 1946,
            'startColumn' => 58,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1946,
            'endLine' => 1946,
            'startColumn' => 69,
            'endColumn' => 74,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1946,
                'endLine' => 1946,
                'startTokenPos' => 8199,
                'startFilePos' => 61888,
                'endTokenPos' => 8199,
                'endFilePos' => 61892,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1946,
            'endLine' => 1946,
            'startColumn' => 77,
            'endColumn' => 92,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a date based (year, month, day, time) statement to the query.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1946,
        'endLine' => 1955,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNested' => 
      array (
        'name' => 'whereNested',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1963,
            'endLine' => 1963,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1963,
                'endLine' => 1963,
                'startTokenPos' => 8281,
                'startFilePos' => 62319,
                'endTokenPos' => 8281,
                'endFilePos' => 62323,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1963,
            'endLine' => 1963,
            'startColumn' => 52,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a nested "where" statement to the query.
 *
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1963,
        'endLine' => 1968,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forNestedWhere' => 
      array (
        'name' => 'forNestedWhere',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new query instance for nested where condition.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 1975,
        'endLine' => 1978,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addNestedWhereQuery' => 
      array (
        'name' => 'addNestedWhereQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1987,
            'endLine' => 1987,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1987,
                'endLine' => 1987,
                'startTokenPos' => 8360,
                'startFilePos' => 62959,
                'endTokenPos' => 8360,
                'endFilePos' => 62963,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1987,
            'endLine' => 1987,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add another query builder as a nested where to the query builder.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder  $query
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1987,
        'endLine' => 1998,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereSub' => 
      array (
        'name' => 'whereSub',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2009,
            'endLine' => 2009,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2009,
            'endLine' => 2009,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2009,
            'endLine' => 2009,
            'startColumn' => 53,
            'endColumn' => 61,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2009,
            'endLine' => 2009,
            'startColumn' => 64,
            'endColumn' => 71,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a full sub-select to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $operator
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2009,
        'endLine' => 2029,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereExists' => 
      array (
        'name' => 'whereExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2039,
            'endLine' => 2039,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2039,
                'endLine' => 2039,
                'startTokenPos' => 8589,
                'startFilePos' => 64654,
                'endTokenPos' => 8589,
                'endFilePos' => 64658,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2039,
            'endLine' => 2039,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2039,
                'endLine' => 2039,
                'startTokenPos' => 8596,
                'startFilePos' => 64668,
                'endTokenPos' => 8596,
                'endFilePos' => 64672,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2039,
            'endLine' => 2039,
            'startColumn' => 62,
            'endColumn' => 73,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2039,
        'endLine' => 2053,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereExists' => 
      array (
        'name' => 'orWhereExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2062,
            'endLine' => 2062,
            'startColumn' => 35,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2062,
                'endLine' => 2062,
                'startTokenPos' => 8700,
                'startFilePos' => 65536,
                'endTokenPos' => 8700,
                'endFilePos' => 65540,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2062,
            'endLine' => 2062,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2062,
        'endLine' => 2065,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotExists' => 
      array (
        'name' => 'whereNotExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2074,
            'endLine' => 2074,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2074,
                'endLine' => 2074,
                'startTokenPos' => 8738,
                'startFilePos' => 65911,
                'endTokenPos' => 8738,
                'endFilePos' => 65915,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2074,
            'endLine' => 2074,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2074,
        'endLine' => 2077,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotExists' => 
      array (
        'name' => 'orWhereNotExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2085,
            'endLine' => 2085,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @return $this
 */',
        'startLine' => 2085,
        'endLine' => 2088,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addWhereExistsQuery' => 
      array (
        'name' => 'addWhereExistsQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2097,
            'endLine' => 2097,
            'startColumn' => 41,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2097,
                'endLine' => 2097,
                'startTokenPos' => 8806,
                'startFilePos' => 66530,
                'endTokenPos' => 8806,
                'endFilePos' => 66534,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2097,
            'endLine' => 2097,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2097,
                'endLine' => 2097,
                'startTokenPos' => 8813,
                'startFilePos' => 66544,
                'endTokenPos' => 8813,
                'endFilePos' => 66548,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2097,
            'endLine' => 2097,
            'startColumn' => 72,
            'endColumn' => 83,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "exists" clause to the query.
 *
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2097,
        'endLine' => 2106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereRowValues' => 
      array (
        'name' => 'whereRowValues',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2119,
            'endLine' => 2119,
            'startColumn' => 36,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2119,
            'endLine' => 2119,
            'startColumn' => 46,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2119,
            'endLine' => 2119,
            'startColumn' => 57,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2119,
                'endLine' => 2119,
                'startTokenPos' => 8896,
                'startFilePos' => 67100,
                'endTokenPos' => 8896,
                'endFilePos' => 67104,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2119,
            'endLine' => 2119,
            'startColumn' => 66,
            'endColumn' => 81,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adds a where condition using row values.
 *
 * @param  array  $columns
 * @param  string  $operator
 * @param  array  $values
 * @param  string  $boolean
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 2119,
        'endLine' => 2132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereRowValues' => 
      array (
        'name' => 'orWhereRowValues',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2142,
            'endLine' => 2142,
            'startColumn' => 38,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2142,
            'endLine' => 2142,
            'startColumn' => 48,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2142,
            'endLine' => 2142,
            'startColumn' => 59,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adds an or where condition using row values.
 *
 * @param  array  $columns
 * @param  string  $operator
 * @param  array  $values
 * @return $this
 */',
        'startLine' => 2142,
        'endLine' => 2145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonContains' => 
      array (
        'name' => 'whereJsonContains',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 48,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 9042,
                'startFilePos' => 68113,
                'endTokenPos' => 9042,
                'endFilePos' => 68117,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 56,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2156,
                'endLine' => 2156,
                'startTokenPos' => 9049,
                'startFilePos' => 68127,
                'endTokenPos' => 9049,
                'endFilePos' => 68131,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2156,
            'endLine' => 2156,
            'startColumn' => 74,
            'endColumn' => 85,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where JSON contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2156,
        'endLine' => 2167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonContains' => 
      array (
        'name' => 'orWhereJsonContains',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2176,
            'endLine' => 2176,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2176,
            'endLine' => 2176,
            'startColumn' => 50,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where JSON contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2176,
        'endLine' => 2179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntContain' => 
      array (
        'name' => 'whereJsonDoesntContain',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2189,
            'endLine' => 2189,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2189,
            'endLine' => 2189,
            'startColumn' => 53,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2189,
                'endLine' => 2189,
                'startTokenPos' => 9177,
                'startFilePos' => 68999,
                'endTokenPos' => 9177,
                'endFilePos' => 69003,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2189,
            'endLine' => 2189,
            'startColumn' => 61,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where JSON not contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2189,
        'endLine' => 2192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntContain' => 
      array (
        'name' => 'orWhereJsonDoesntContain',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2201,
            'endLine' => 2201,
            'startColumn' => 46,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2201,
            'endLine' => 2201,
            'startColumn' => 55,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where JSON not contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2201,
        'endLine' => 2204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonOverlaps' => 
      array (
        'name' => 'whereJsonOverlaps',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2215,
            'endLine' => 2215,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2215,
            'endLine' => 2215,
            'startColumn' => 48,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2215,
                'endLine' => 2215,
                'startTokenPos' => 9255,
                'startFilePos' => 69689,
                'endTokenPos' => 9255,
                'endFilePos' => 69693,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2215,
            'endLine' => 2215,
            'startColumn' => 56,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2215,
                'endLine' => 2215,
                'startTokenPos' => 9262,
                'startFilePos' => 69703,
                'endTokenPos' => 9262,
                'endFilePos' => 69707,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2215,
            'endLine' => 2215,
            'startColumn' => 74,
            'endColumn' => 85,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where JSON overlaps" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2215,
        'endLine' => 2226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonOverlaps' => 
      array (
        'name' => 'orWhereJsonOverlaps',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2235,
            'endLine' => 2235,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2235,
            'endLine' => 2235,
            'startColumn' => 50,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where JSON overlaps" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2235,
        'endLine' => 2238,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntOverlap' => 
      array (
        'name' => 'whereJsonDoesntOverlap',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2248,
            'endLine' => 2248,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2248,
            'endLine' => 2248,
            'startColumn' => 53,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2248,
                'endLine' => 2248,
                'startTokenPos' => 9390,
                'startFilePos' => 70574,
                'endTokenPos' => 9390,
                'endFilePos' => 70578,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2248,
            'endLine' => 2248,
            'startColumn' => 61,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where JSON not overlap" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2248,
        'endLine' => 2251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntOverlap' => 
      array (
        'name' => 'orWhereJsonDoesntOverlap',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 46,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 55,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where JSON not overlap" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2260,
        'endLine' => 2263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonContainsKey' => 
      array (
        'name' => 'whereJsonContainsKey',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2273,
            'endLine' => 2273,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2273,
                'endLine' => 2273,
                'startTokenPos' => 9465,
                'startFilePos' => 71245,
                'endTokenPos' => 9465,
                'endFilePos' => 71249,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2273,
            'endLine' => 2273,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2273,
                'endLine' => 2273,
                'startTokenPos' => 9472,
                'startFilePos' => 71259,
                'endTokenPos' => 9472,
                'endFilePos' => 71263,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2273,
            'endLine' => 2273,
            'startColumn' => 69,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a clause that determines if a JSON path exists to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2273,
        'endLine' => 2280,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonContainsKey' => 
      array (
        'name' => 'orWhereJsonContainsKey',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2288,
            'endLine' => 2288,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or" clause that determines if a JSON path exists to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2288,
        'endLine' => 2291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntContainKey' => 
      array (
        'name' => 'whereJsonDoesntContainKey',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2300,
            'endLine' => 2300,
            'startColumn' => 47,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2300,
                'endLine' => 2300,
                'startTokenPos' => 9557,
                'startFilePos' => 71939,
                'endTokenPos' => 9557,
                'endFilePos' => 71943,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2300,
            'endLine' => 2300,
            'startColumn' => 56,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a clause that determines if a JSON path does not exist to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2300,
        'endLine' => 2303,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntContainKey' => 
      array (
        'name' => 'orWhereJsonDoesntContainKey',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2311,
            'endLine' => 2311,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or" clause that determines if a JSON path does not exist to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2311,
        'endLine' => 2314,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonLength' => 
      array (
        'name' => 'whereJsonLength',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2325,
            'endLine' => 2325,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2325,
            'endLine' => 2325,
            'startColumn' => 46,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2325,
                'endLine' => 2325,
                'startTokenPos' => 9626,
                'startFilePos' => 72610,
                'endTokenPos' => 9626,
                'endFilePos' => 72613,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2325,
            'endLine' => 2325,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2325,
                'endLine' => 2325,
                'startTokenPos' => 9633,
                'startFilePos' => 72627,
                'endTokenPos' => 9633,
                'endFilePos' => 72631,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2325,
            'endLine' => 2325,
            'startColumn' => 72,
            'endColumn' => 87,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where JSON length" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2325,
        'endLine' => 2347,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonLength' => 
      array (
        'name' => 'orWhereJsonLength',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2357,
            'endLine' => 2357,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2357,
            'endLine' => 2357,
            'startColumn' => 48,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2357,
                'endLine' => 2357,
                'startTokenPos' => 9796,
                'startFilePos' => 73661,
                'endTokenPos' => 9796,
                'endFilePos' => 73664,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2357,
            'endLine' => 2357,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where JSON length" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2357,
        'endLine' => 2364,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dynamicWhere' => 
      array (
        'name' => 'dynamicWhere',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2373,
            'endLine' => 2373,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2373,
            'endLine' => 2373,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handles dynamic "where" clauses to the query.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return $this
 */',
        'startLine' => 2373,
        'endLine' => 2407,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addDynamic' => 
      array (
        'name' => 'addDynamic',
        'parameters' => 
        array (
          'segment' => 
          array (
            'name' => 'segment',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2418,
            'endLine' => 2418,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'connector' => 
          array (
            'name' => 'connector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2418,
            'endLine' => 2418,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2418,
            'endLine' => 2418,
            'startColumn' => 57,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2418,
            'endLine' => 2418,
            'startColumn' => 70,
            'endColumn' => 75,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a single dynamic "where" clause statement to the query.
 *
 * @param  string  $segment
 * @param  string  $connector
 * @param  array  $parameters
 * @param  int  $index
 * @return void
 */',
        'startLine' => 2418,
        'endLine' => 2426,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereFullText' => 
      array (
        'name' => 'whereFullText',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2436,
            'endLine' => 2436,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2436,
            'endLine' => 2436,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2436,
                'endLine' => 2436,
                'startTokenPos' => 10098,
                'startFilePos' => 76402,
                'endTokenPos' => 10099,
                'endFilePos' => 76403,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2436,
            'endLine' => 2436,
            'startColumn' => 53,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2436,
                'endLine' => 2436,
                'startTokenPos' => 10106,
                'startFilePos' => 76417,
                'endTokenPos' => 10106,
                'endFilePos' => 76421,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2436,
            'endLine' => 2436,
            'startColumn' => 74,
            'endColumn' => 89,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where fulltext" clause to the query.
 *
 * @param  string|string[]  $columns
 * @param  string  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2436,
        'endLine' => 2447,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereFullText' => 
      array (
        'name' => 'orWhereFullText',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2456,
            'endLine' => 2456,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2456,
            'endLine' => 2456,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2456,
                'endLine' => 2456,
                'startTokenPos' => 10188,
                'startFilePos' => 76889,
                'endTokenPos' => 10189,
                'endFilePos' => 76890,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2456,
            'endLine' => 2456,
            'startColumn' => 55,
            'endColumn' => 73,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where fulltext" clause to the query.
 *
 * @param  string|string[]  $columns
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 2456,
        'endLine' => 2459,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereAll' => 
      array (
        'name' => 'whereAll',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2470,
            'endLine' => 2470,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2470,
                'endLine' => 2470,
                'startTokenPos' => 10230,
                'startFilePos' => 77358,
                'endTokenPos' => 10230,
                'endFilePos' => 77361,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2470,
            'endLine' => 2470,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2470,
                'endLine' => 2470,
                'startTokenPos' => 10237,
                'startFilePos' => 77373,
                'endTokenPos' => 10237,
                'endFilePos' => 77376,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2470,
            'endLine' => 2470,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2470,
                'endLine' => 2470,
                'startTokenPos' => 10244,
                'startFilePos' => 77390,
                'endTokenPos' => 10244,
                'endFilePos' => 77394,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2470,
            'endLine' => 2470,
            'startColumn' => 73,
            'endColumn' => 88,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where" clause to the query for multiple columns with "and" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2470,
        'endLine' => 2483,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereAll' => 
      array (
        'name' => 'orWhereAll',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2493,
            'endLine' => 2493,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2493,
                'endLine' => 2493,
                'startTokenPos' => 10364,
                'startFilePos' => 78142,
                'endTokenPos' => 10364,
                'endFilePos' => 78145,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2493,
            'endLine' => 2493,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2493,
                'endLine' => 2493,
                'startTokenPos' => 10371,
                'startFilePos' => 78157,
                'endTokenPos' => 10371,
                'endFilePos' => 78160,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2493,
            'endLine' => 2493,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where" clause to the query for multiple columns with "and" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2493,
        'endLine' => 2496,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereAny' => 
      array (
        'name' => 'whereAny',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2507,
            'endLine' => 2507,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2507,
                'endLine' => 2507,
                'startTokenPos' => 10412,
                'startFilePos' => 78623,
                'endTokenPos' => 10412,
                'endFilePos' => 78626,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2507,
            'endLine' => 2507,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2507,
                'endLine' => 2507,
                'startTokenPos' => 10419,
                'startFilePos' => 78638,
                'endTokenPos' => 10419,
                'endFilePos' => 78641,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2507,
            'endLine' => 2507,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2507,
                'endLine' => 2507,
                'startTokenPos' => 10426,
                'startFilePos' => 78655,
                'endTokenPos' => 10426,
                'endFilePos' => 78659,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2507,
            'endLine' => 2507,
            'startColumn' => 73,
            'endColumn' => 88,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where" clause to the query for multiple columns with "or" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2507,
        'endLine' => 2520,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereAny' => 
      array (
        'name' => 'orWhereAny',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2530,
            'endLine' => 2530,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2530,
                'endLine' => 2530,
                'startTokenPos' => 10546,
                'startFilePos' => 79405,
                'endTokenPos' => 10546,
                'endFilePos' => 79408,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2530,
            'endLine' => 2530,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2530,
                'endLine' => 2530,
                'startTokenPos' => 10553,
                'startFilePos' => 79420,
                'endTokenPos' => 10553,
                'endFilePos' => 79423,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2530,
            'endLine' => 2530,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where" clause to the query for multiple columns with "or" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2530,
        'endLine' => 2533,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNone' => 
      array (
        'name' => 'whereNone',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2544,
            'endLine' => 2544,
            'startColumn' => 31,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2544,
                'endLine' => 2544,
                'startTokenPos' => 10594,
                'startFilePos' => 79901,
                'endTokenPos' => 10594,
                'endFilePos' => 79904,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2544,
            'endLine' => 2544,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2544,
                'endLine' => 2544,
                'startTokenPos' => 10601,
                'startFilePos' => 79916,
                'endTokenPos' => 10601,
                'endFilePos' => 79919,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2544,
            'endLine' => 2544,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2544,
                'endLine' => 2544,
                'startTokenPos' => 10608,
                'startFilePos' => 79933,
                'endTokenPos' => 10608,
                'endFilePos' => 79937,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2544,
            'endLine' => 2544,
            'startColumn' => 74,
            'endColumn' => 89,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "where not" clause to the query for multiple columns where none of the conditions should be true.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2544,
        'endLine' => 2547,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNone' => 
      array (
        'name' => 'orWhereNone',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2557,
            'endLine' => 2557,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2557,
                'endLine' => 2557,
                'startTokenPos' => 10651,
                'startFilePos' => 80400,
                'endTokenPos' => 10651,
                'endFilePos' => 80403,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2557,
            'endLine' => 2557,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2557,
                'endLine' => 2557,
                'startTokenPos' => 10658,
                'startFilePos' => 80415,
                'endTokenPos' => 10658,
                'endFilePos' => 80418,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2557,
            'endLine' => 2557,
            'startColumn' => 61,
            'endColumn' => 73,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or where not" clause to the query for multiple columns where none of the conditions should be true.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2557,
        'endLine' => 2560,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupBy' => 
      array (
        'name' => 'groupBy',
        'parameters' => 
        array (
          'groups' => 
          array (
            'name' => 'groups',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2568,
            'endLine' => 2568,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "group by" clause to the query.
 *
 * @param  array|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  ...$groups
 * @return $this
 */',
        'startLine' => 2568,
        'endLine' => 2578,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupByRaw' => 
      array (
        'name' => 'groupByRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2586,
            'endLine' => 2586,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2586,
                'endLine' => 2586,
                'startTokenPos' => 10762,
                'startFilePos' => 81107,
                'endTokenPos' => 10763,
                'endFilePos' => 81108,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2586,
            'endLine' => 2586,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "groupBy" clause to the query.
 *
 * @param  string  $sql
 * @return $this
 */',
        'startLine' => 2586,
        'endLine' => 2593,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'having' => 
      array (
        'name' => 'having',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2604,
            'endLine' => 2604,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2604,
                'endLine' => 2604,
                'startTokenPos' => 10817,
                'startFilePos' => 81679,
                'endTokenPos' => 10817,
                'endFilePos' => 81682,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2604,
            'endLine' => 2604,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2604,
                'endLine' => 2604,
                'startTokenPos' => 10824,
                'startFilePos' => 81694,
                'endTokenPos' => 10824,
                'endFilePos' => 81697,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2604,
            'endLine' => 2604,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2604,
                'endLine' => 2604,
                'startTokenPos' => 10831,
                'startFilePos' => 81711,
                'endTokenPos' => 10831,
                'endFilePos' => 81715,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2604,
            'endLine' => 2604,
            'startColumn' => 70,
            'endColumn' => 85,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "having" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\Closure|string  $column
 * @param  \\DateTimeInterface|string|int|float|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\DateTimeInterface|string|int|float|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2604,
        'endLine' => 2645,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHaving' => 
      array (
        'name' => 'orHaving',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2655,
                'endLine' => 2655,
                'startTokenPos' => 11100,
                'startFilePos' => 83568,
                'endTokenPos' => 11100,
                'endFilePos' => 83571,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2655,
                'endLine' => 2655,
                'startTokenPos' => 11107,
                'startFilePos' => 83583,
                'endTokenPos' => 11107,
                'endFilePos' => 83586,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or having" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\Closure|string  $column
 * @param  \\DateTimeInterface|string|int|float|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\DateTimeInterface|string|int|float|null  $value
 * @return $this
 */',
        'startLine' => 2655,
        'endLine' => 2662,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNested' => 
      array (
        'name' => 'havingNested',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2670,
            'endLine' => 2670,
            'startColumn' => 34,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2670,
                'endLine' => 2670,
                'startTokenPos' => 11181,
                'startFilePos' => 83985,
                'endTokenPos' => 11181,
                'endFilePos' => 83989,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2670,
            'endLine' => 2670,
            'startColumn' => 53,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a nested "having" statement to the query.
 *
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2670,
        'endLine' => 2675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addNestedHavingQuery' => 
      array (
        'name' => 'addNestedHavingQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2684,
            'endLine' => 2684,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2684,
                'endLine' => 2684,
                'startTokenPos' => 11230,
                'startFilePos' => 84389,
                'endTokenPos' => 11230,
                'endFilePos' => 84393,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2684,
            'endLine' => 2684,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add another query builder as a nested having to the query builder.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder  $query
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2684,
        'endLine' => 2695,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNull' => 
      array (
        'name' => 'havingNull',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2705,
            'endLine' => 2705,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2705,
                'endLine' => 2705,
                'startTokenPos' => 11317,
                'startFilePos' => 84897,
                'endTokenPos' => 11317,
                'endFilePos' => 84901,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2705,
            'endLine' => 2705,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2705,
                'endLine' => 2705,
                'startTokenPos' => 11324,
                'startFilePos' => 84911,
                'endTokenPos' => 11324,
                'endFilePos' => 84915,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2705,
            'endLine' => 2705,
            'startColumn' => 60,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "having null" clause to the query.
 *
 * @param  array|string  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2705,
        'endLine' => 2714,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNull' => 
      array (
        'name' => 'orHavingNull',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2722,
            'endLine' => 2722,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or having null" clause to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2722,
        'endLine' => 2725,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNotNull' => 
      array (
        'name' => 'havingNotNull',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2734,
            'endLine' => 2734,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2734,
                'endLine' => 2734,
                'startTokenPos' => 11433,
                'startFilePos' => 85580,
                'endTokenPos' => 11433,
                'endFilePos' => 85584,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2734,
            'endLine' => 2734,
            'startColumn' => 45,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "having not null" clause to the query.
 *
 * @param  array|string  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2734,
        'endLine' => 2737,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNotNull' => 
      array (
        'name' => 'orHavingNotNull',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2745,
            'endLine' => 2745,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or having not null" clause to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2745,
        'endLine' => 2748,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingBetween' => 
      array (
        'name' => 'havingBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2758,
            'endLine' => 2758,
            'startColumn' => 35,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2758,
            'endLine' => 2758,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2758,
                'endLine' => 2758,
                'startTokenPos' => 11504,
                'startFilePos' => 86157,
                'endTokenPos' => 11504,
                'endFilePos' => 86161,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2758,
            'endLine' => 2758,
            'startColumn' => 62,
            'endColumn' => 77,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2758,
                'endLine' => 2758,
                'startTokenPos' => 11511,
                'startFilePos' => 86171,
                'endTokenPos' => 11511,
                'endFilePos' => 86175,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2758,
            'endLine' => 2758,
            'startColumn' => 80,
            'endColumn' => 91,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "having between" clause to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2758,
        'endLine' => 2771,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNotBetween' => 
      array (
        'name' => 'havingNotBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2781,
            'endLine' => 2781,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2781,
            'endLine' => 2781,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2781,
                'endLine' => 2781,
                'startTokenPos' => 11640,
                'startFilePos' => 86826,
                'endTokenPos' => 11640,
                'endFilePos' => 86830,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2781,
            'endLine' => 2781,
            'startColumn' => 65,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "having not between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2781,
        'endLine' => 2784,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingBetween' => 
      array (
        'name' => 'orHavingBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2793,
            'endLine' => 2793,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2793,
            'endLine' => 2793,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or having between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @return $this
 */',
        'startLine' => 2793,
        'endLine' => 2796,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNotBetween' => 
      array (
        'name' => 'orHavingNotBetween',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2805,
            'endLine' => 2805,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2805,
            'endLine' => 2805,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "or having not between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @return $this
 */',
        'startLine' => 2805,
        'endLine' => 2808,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingRaw' => 
      array (
        'name' => 'havingRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2817,
            'endLine' => 2817,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2817,
                'endLine' => 2817,
                'startTokenPos' => 11758,
                'startFilePos' => 87735,
                'endTokenPos' => 11759,
                'endFilePos' => 87736,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2817,
            'endLine' => 2817,
            'startColumn' => 37,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2817,
                'endLine' => 2817,
                'startTokenPos' => 11766,
                'startFilePos' => 87750,
                'endTokenPos' => 11766,
                'endFilePos' => 87754,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2817,
            'endLine' => 2817,
            'startColumn' => 59,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "having" clause to the query.
 *
 * @param  string  $sql
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2817,
        'endLine' => 2826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingRaw' => 
      array (
        'name' => 'orHavingRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2834,
            'endLine' => 2834,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2834,
                'endLine' => 2834,
                'startTokenPos' => 11833,
                'startFilePos' => 88106,
                'endTokenPos' => 11834,
                'endFilePos' => 88107,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2834,
            'endLine' => 2834,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "or having" clause to the query.
 *
 * @param  string  $sql
 * @return $this
 */',
        'startLine' => 2834,
        'endLine' => 2837,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderBy' => 
      array (
        'name' => 'orderBy',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2848,
            'endLine' => 2848,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'direction' => 
          array (
            'name' => 'direction',
            'default' => 
            array (
              'code' => '\'asc\'',
              'attributes' => 
              array (
                'startLine' => 2848,
                'endLine' => 2848,
                'startTokenPos' => 11872,
                'startFilePos' => 88565,
                'endTokenPos' => 11872,
                'endFilePos' => 88569,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2848,
            'endLine' => 2848,
            'startColumn' => 38,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "order by" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $direction
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 2848,
        'endLine' => 2870,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByDesc' => 
      array (
        'name' => 'orderByDesc',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2878,
            'endLine' => 2878,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a descending "order by" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 2878,
        'endLine' => 2881,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'latest' => 
      array (
        'name' => 'latest',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'created_at\'',
              'attributes' => 
              array (
                'startLine' => 2889,
                'endLine' => 2889,
                'startTokenPos' => 12078,
                'startFilePos' => 89850,
                'endTokenPos' => 12078,
                'endFilePos' => 89861,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2889,
            'endLine' => 2889,
            'startColumn' => 28,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "order by" clause for a timestamp to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 2889,
        'endLine' => 2892,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'oldest' => 
      array (
        'name' => 'oldest',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'created_at\'',
              'attributes' => 
              array (
                'startLine' => 2900,
                'endLine' => 2900,
                'startTokenPos' => 12110,
                'startFilePos' => 90191,
                'endTokenPos' => 12110,
                'endFilePos' => 90202,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2900,
            'endLine' => 2900,
            'startColumn' => 28,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add an "order by" clause for a timestamp to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 2900,
        'endLine' => 2903,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByVectorDistance' => 
      array (
        'name' => 'orderByVectorDistance',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2912,
            'endLine' => 2912,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2912,
            'endLine' => 2912,
            'startColumn' => 52,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a vector-distance "order by" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>  $vector
 * @return $this
 */',
        'startLine' => 2912,
        'endLine' => 2936,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'inRandomOrder' => 
      array (
        'name' => 'inRandomOrder',
        'parameters' => 
        array (
          'seed' => 
          array (
            'name' => 'seed',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 2944,
                'endLine' => 2944,
                'startTokenPos' => 12314,
                'startFilePos' => 91487,
                'endTokenPos' => 12314,
                'endFilePos' => 91488,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2944,
            'endLine' => 2944,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Put the query\'s results in random order.
 *
 * @param  string|int  $seed
 * @return $this
 */',
        'startLine' => 2944,
        'endLine' => 2947,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByRaw' => 
      array (
        'name' => 'orderByRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2956,
            'endLine' => 2956,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2956,
                'endLine' => 2956,
                'startTokenPos' => 12353,
                'startFilePos' => 91778,
                'endTokenPos' => 12354,
                'endFilePos' => 91779,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2956,
            'endLine' => 2956,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a raw "order by" clause to the query.
 *
 * @param  string  $sql
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 2956,
        'endLine' => 2965,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'skip' => 
      array (
        'name' => 'skip',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2973,
            'endLine' => 2973,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Alias to set the "offset" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 2973,
        'endLine' => 2976,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'offset' => 
      array (
        'name' => 'offset',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2984,
            'endLine' => 2984,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the "offset" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 2984,
        'endLine' => 2991,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'take' => 
      array (
        'name' => 'take',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2999,
            'endLine' => 2999,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Alias to set the "limit" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 2999,
        'endLine' => 3002,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'limit' => 
      array (
        'name' => 'limit',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3010,
            'endLine' => 3010,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the "limit" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 3010,
        'endLine' => 3019,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupLimit' => 
      array (
        'name' => 'groupLimit',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3028,
            'endLine' => 3028,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3028,
            'endLine' => 3028,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "group limit" clause to the query.
 *
 * @param  int  $value
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3028,
        'endLine' => 3035,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPage' => 
      array (
        'name' => 'forPage',
        'parameters' => 
        array (
          'page' => 
          array (
            'name' => 'page',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3044,
            'endLine' => 3044,
            'startColumn' => 29,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3044,
                'endLine' => 3044,
                'startTokenPos' => 12669,
                'startFilePos' => 93599,
                'endTokenPos' => 12669,
                'endFilePos' => 93600,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3044,
            'endLine' => 3044,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the limit and offset for a given page.
 *
 * @param  int  $page
 * @param  int  $perPage
 * @return $this
 */',
        'startLine' => 3044,
        'endLine' => 3047,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPageBeforeId' => 
      array (
        'name' => 'forPageBeforeId',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3057,
                'endLine' => 3057,
                'startTokenPos' => 12713,
                'startFilePos' => 93951,
                'endTokenPos' => 12713,
                'endFilePos' => 93952,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3057,
            'endLine' => 3057,
            'startColumn' => 37,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'lastId' => 
          array (
            'name' => 'lastId',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 3057,
                'endLine' => 3057,
                'startTokenPos' => 12720,
                'startFilePos' => 93965,
                'endTokenPos' => 12720,
                'endFilePos' => 93965,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3057,
            'endLine' => 3057,
            'startColumn' => 52,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'id\'',
              'attributes' => 
              array (
                'startLine' => 3057,
                'endLine' => 3057,
                'startTokenPos' => 12727,
                'startFilePos' => 93978,
                'endTokenPos' => 12727,
                'endFilePos' => 93981,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3057,
            'endLine' => 3057,
            'startColumn' => 65,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constrain the query to the previous "page" of results before a given ID.
 *
 * @param  int  $perPage
 * @param  int|null  $lastId
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3057,
        'endLine' => 3069,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPageAfterId' => 
      array (
        'name' => 'forPageAfterId',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3079,
                'endLine' => 3079,
                'startTokenPos' => 12820,
                'startFilePos' => 94550,
                'endTokenPos' => 12820,
                'endFilePos' => 94551,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3079,
            'endLine' => 3079,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'lastId' => 
          array (
            'name' => 'lastId',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 3079,
                'endLine' => 3079,
                'startTokenPos' => 12827,
                'startFilePos' => 94564,
                'endTokenPos' => 12827,
                'endFilePos' => 94564,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3079,
            'endLine' => 3079,
            'startColumn' => 51,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'id\'',
              'attributes' => 
              array (
                'startLine' => 3079,
                'endLine' => 3079,
                'startTokenPos' => 12834,
                'startFilePos' => 94577,
                'endTokenPos' => 12834,
                'endFilePos' => 94580,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3079,
            'endLine' => 3079,
            'startColumn' => 64,
            'endColumn' => 77,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constrain the query to the next "page" of results after a given ID.
 *
 * @param  int  $perPage
 * @param  int|null  $lastId
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3079,
        'endLine' => 3091,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'reorder' => 
      array (
        'name' => 'reorder',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3100,
                'endLine' => 3100,
                'startTokenPos' => 12927,
                'startFilePos' => 95200,
                'endTokenPos' => 12927,
                'endFilePos' => 95203,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3100,
            'endLine' => 3100,
            'startColumn' => 29,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'direction' => 
          array (
            'name' => 'direction',
            'default' => 
            array (
              'code' => '\'asc\'',
              'attributes' => 
              array (
                'startLine' => 3100,
                'endLine' => 3100,
                'startTokenPos' => 12934,
                'startFilePos' => 95219,
                'endTokenPos' => 12934,
                'endFilePos' => 95223,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3100,
            'endLine' => 3100,
            'startColumn' => 45,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove all existing orders and optionally add a new order.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $column
 * @param  string  $direction
 * @return $this
 */',
        'startLine' => 3100,
        'endLine' => 3112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'reorderDesc' => 
      array (
        'name' => 'reorderDesc',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3120,
            'endLine' => 3120,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add descending "reorder" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $column
 * @return $this
 */',
        'startLine' => 3120,
        'endLine' => 3123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'removeExistingOrdersFor' => 
      array (
        'name' => 'removeExistingOrdersFor',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3131,
            'endLine' => 3131,
            'startColumn' => 48,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get an array with all orders with a given column removed.
 *
 * @param  string  $column
 * @return array
 */',
        'startLine' => 3131,
        'endLine' => 3137,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'union' => 
      array (
        'name' => 'union',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3146,
            'endLine' => 3146,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'all' => 
          array (
            'name' => 'all',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 3146,
                'endLine' => 3146,
                'startTokenPos' => 13126,
                'startFilePos' => 96484,
                'endTokenPos' => 13126,
                'endFilePos' => 96488,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3146,
            'endLine' => 3146,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "union" statement to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $query
 * @param  bool  $all
 * @return $this
 */',
        'startLine' => 3146,
        'endLine' => 3157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'unionAll' => 
      array (
        'name' => 'unionAll',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3165,
            'endLine' => 3165,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "union all" statement to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $query
 * @return $this
 */',
        'startLine' => 3165,
        'endLine' => 3168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'lock' => 
      array (
        'name' => 'lock',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 3176,
                'endLine' => 3176,
                'startTokenPos' => 13238,
                'startFilePos' => 97189,
                'endTokenPos' => 13238,
                'endFilePos' => 97192,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3176,
            'endLine' => 3176,
            'startColumn' => 26,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Lock the selected rows in the table.
 *
 * @param  string|bool  $value
 * @return $this
 */',
        'startLine' => 3176,
        'endLine' => 3185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'lockForUpdate' => 
      array (
        'name' => 'lockForUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Lock the selected rows in the table for updating.
 *
 * @return $this
 */',
        'startLine' => 3192,
        'endLine' => 3195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'sharedLock' => 
      array (
        'name' => 'sharedLock',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Share lock the selected rows in the table.
 *
 * @return $this
 */',
        'startLine' => 3202,
        'endLine' => 3205,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'beforeQuery' => 
      array (
        'name' => 'beforeQuery',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3212,
            'endLine' => 3212,
            'startColumn' => 33,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a closure to be invoked before the query is executed.
 *
 * @return $this
 */',
        'startLine' => 3212,
        'endLine' => 3217,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'applyBeforeQueryCallbacks' => 
      array (
        'name' => 'applyBeforeQueryCallbacks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Invoke the "before query" modification callbacks.
 *
 * @return void
 */',
        'startLine' => 3224,
        'endLine' => 3231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'afterQuery' => 
      array (
        'name' => 'afterQuery',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3238,
            'endLine' => 3238,
            'startColumn' => 32,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a closure to be invoked after the query is executed.
 *
 * @return $this
 */',
        'startLine' => 3238,
        'endLine' => 3243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'applyAfterQueryCallbacks' => 
      array (
        'name' => 'applyAfterQueryCallbacks',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3251,
            'endLine' => 3251,
            'startColumn' => 46,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Invoke the "after query" modification callbacks.
 *
 * @param  mixed  $result
 * @return mixed
 */',
        'startLine' => 3251,
        'endLine' => 3258,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'toSql' => 
      array (
        'name' => 'toSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL representation of the query.
 *
 * @return string
 */',
        'startLine' => 3265,
        'endLine' => 3270,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'toRawSql' => 
      array (
        'name' => 'toRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the raw SQL representation of the query with embedded bindings.
 *
 * @return string
 */',
        'startLine' => 3277,
        'endLine' => 3282,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'find' => 
      array (
        'name' => 'find',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3291,
            'endLine' => 3291,
            'startColumn' => 26,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3291,
                'endLine' => 3291,
                'startTokenPos' => 13588,
                'startFilePos' => 99745,
                'endTokenPos' => 13590,
                'endFilePos' => 99749,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3291,
            'endLine' => 3291,
            'startColumn' => 31,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute a query for a single record by ID.
 *
 * @param  int|string  $id
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return \\stdClass|null
 */',
        'startLine' => 3291,
        'endLine' => 3294,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'findOr' => 
      array (
        'name' => 'findOr',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3306,
            'endLine' => 3306,
            'startColumn' => 28,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3306,
                'endLine' => 3306,
                'startTokenPos' => 13633,
                'startFilePos' => 100266,
                'endTokenPos' => 13635,
                'endFilePos' => 100270,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3306,
            'endLine' => 3306,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3306,
                'endLine' => 3306,
                'startTokenPos' => 13645,
                'startFilePos' => 100294,
                'endTokenPos' => 13645,
                'endFilePos' => 100297,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Closure',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3306,
            'endLine' => 3306,
            'startColumn' => 51,
            'endColumn' => 75,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute a query for a single record by ID or call a callback.
 *
 * @template TValue
 *
 * @param  mixed  $id
 * @param  (\\Closure(): TValue)|string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  (\\Closure(): TValue)|null  $callback
 * @return \\stdClass|TValue
 */',
        'startLine' => 3306,
        'endLine' => 3319,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'value' => 
      array (
        'name' => 'value',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3327,
            'endLine' => 3327,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a single column\'s value from the first result of a query.
 *
 * @param  string  $column
 * @return mixed
 */',
        'startLine' => 3327,
        'endLine' => 3332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rawValue' => 
      array (
        'name' => 'rawValue',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3339,
            'endLine' => 3339,
            'startColumn' => 30,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 3339,
                'endLine' => 3339,
                'startTokenPos' => 13794,
                'startFilePos' => 101049,
                'endTokenPos' => 13795,
                'endFilePos' => 101050,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3339,
            'endLine' => 3339,
            'startColumn' => 50,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a single expression value from the first result of a query.
 *
 * @return mixed
 */',
        'startLine' => 3339,
        'endLine' => 3344,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'soleValue' => 
      array (
        'name' => 'soleValue',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3355,
            'endLine' => 3355,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a single column\'s value from the first result of a query if it\'s the sole matching record.
 *
 * @param  string  $column
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\RecordsNotFoundException
 * @throws \\Illuminate\\Database\\MultipleRecordsFoundException
 */',
        'startLine' => 3355,
        'endLine' => 3360,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3368,
                'endLine' => 3368,
                'startTokenPos' => 13897,
                'startFilePos' => 101972,
                'endTokenPos' => 13899,
                'endFilePos' => 101976,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3368,
            'endLine' => 3368,
            'startColumn' => 25,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the query as a "select" statement.
 *
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return \\Illuminate\\Support\\Collection<int, \\stdClass>
 */',
        'startLine' => 3368,
        'endLine' => 3377,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'runSelect' => 
      array (
        'name' => 'runSelect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the query as a "select" statement against the connection.
 *
 * @return array
 */',
        'startLine' => 3384,
        'endLine' => 3389,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'withoutGroupLimitKeys' => 
      array (
        'name' => 'withoutGroupLimitKeys',
        'parameters' => 
        array (
          'items' => 
          array (
            'name' => 'items',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3397,
            'endLine' => 3397,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the group limit keys from the results in the collection.
 *
 * @param  \\Illuminate\\Support\\Collection  $items
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3397,
        'endLine' => 3415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'paginate' => 
      array (
        'name' => 'paginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3427,
                'endLine' => 3427,
                'startTokenPos' => 14195,
                'startFilePos' => 103872,
                'endTokenPos' => 14195,
                'endFilePos' => 103873,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3427,
            'endLine' => 3427,
            'startColumn' => 30,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3427,
                'endLine' => 3427,
                'startTokenPos' => 14202,
                'startFilePos' => 103887,
                'endTokenPos' => 14204,
                'endFilePos' => 103891,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3427,
            'endLine' => 3427,
            'startColumn' => 45,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'pageName' => 
          array (
            'name' => 'pageName',
            'default' => 
            array (
              'code' => '\'page\'',
              'attributes' => 
              array (
                'startLine' => 3427,
                'endLine' => 3427,
                'startTokenPos' => 14211,
                'startFilePos' => 103906,
                'endTokenPos' => 14211,
                'endFilePos' => 103911,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3427,
            'endLine' => 3427,
            'startColumn' => 63,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'page' => 
          array (
            'name' => 'page',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3427,
                'endLine' => 3427,
                'startTokenPos' => 14218,
                'startFilePos' => 103922,
                'endTokenPos' => 14218,
                'endFilePos' => 103925,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3427,
            'endLine' => 3427,
            'startColumn' => 83,
            'endColumn' => 94,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'total' => 
          array (
            'name' => 'total',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3427,
                'endLine' => 3427,
                'startTokenPos' => 14225,
                'startFilePos' => 103937,
                'endTokenPos' => 14225,
                'endFilePos' => 103940,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3427,
            'endLine' => 3427,
            'startColumn' => 97,
            'endColumn' => 109,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Paginate the given query into a simple paginator.
 *
 * @param  int|\\Closure  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $pageName
 * @param  int|null  $page
 * @param  \\Closure|int|null  $total
 * @return \\Illuminate\\Pagination\\LengthAwarePaginator
 */',
        'startLine' => 3427,
        'endLine' => 3441,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'simplePaginate' => 
      array (
        'name' => 'simplePaginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3454,
                'endLine' => 3454,
                'startTokenPos' => 14364,
                'startFilePos' => 104899,
                'endTokenPos' => 14364,
                'endFilePos' => 104900,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3454,
            'endLine' => 3454,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3454,
                'endLine' => 3454,
                'startTokenPos' => 14371,
                'startFilePos' => 104914,
                'endTokenPos' => 14373,
                'endFilePos' => 104918,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3454,
            'endLine' => 3454,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'pageName' => 
          array (
            'name' => 'pageName',
            'default' => 
            array (
              'code' => '\'page\'',
              'attributes' => 
              array (
                'startLine' => 3454,
                'endLine' => 3454,
                'startTokenPos' => 14380,
                'startFilePos' => 104933,
                'endTokenPos' => 14380,
                'endFilePos' => 104938,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3454,
            'endLine' => 3454,
            'startColumn' => 69,
            'endColumn' => 86,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'page' => 
          array (
            'name' => 'page',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3454,
                'endLine' => 3454,
                'startTokenPos' => 14387,
                'startFilePos' => 104949,
                'endTokenPos' => 14387,
                'endFilePos' => 104952,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3454,
            'endLine' => 3454,
            'startColumn' => 89,
            'endColumn' => 100,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a paginator only supporting simple next and previous links.
 *
 * This is more efficient on larger data-sets, etc.
 *
 * @param  int  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $pageName
 * @param  int|null  $page
 * @return \\Illuminate\\Contracts\\Pagination\\Paginator
 */',
        'startLine' => 3454,
        'endLine' => 3464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cursorPaginate' => 
      array (
        'name' => 'cursorPaginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3477,
                'endLine' => 3477,
                'startTokenPos' => 14494,
                'startFilePos' => 105832,
                'endTokenPos' => 14494,
                'endFilePos' => 105833,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3477,
            'endLine' => 3477,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3477,
                'endLine' => 3477,
                'startTokenPos' => 14501,
                'startFilePos' => 105847,
                'endTokenPos' => 14503,
                'endFilePos' => 105851,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3477,
            'endLine' => 3477,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'cursorName' => 
          array (
            'name' => 'cursorName',
            'default' => 
            array (
              'code' => '\'cursor\'',
              'attributes' => 
              array (
                'startLine' => 3477,
                'endLine' => 3477,
                'startTokenPos' => 14510,
                'startFilePos' => 105868,
                'endTokenPos' => 14510,
                'endFilePos' => 105875,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3477,
            'endLine' => 3477,
            'startColumn' => 69,
            'endColumn' => 90,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3477,
                'endLine' => 3477,
                'startTokenPos' => 14517,
                'startFilePos' => 105888,
                'endTokenPos' => 14517,
                'endFilePos' => 105891,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3477,
            'endLine' => 3477,
            'startColumn' => 93,
            'endColumn' => 106,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a paginator only supporting simple next and previous links.
 *
 * This is more efficient on larger data-sets, etc.
 *
 * @param  int|null  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $cursorName
 * @param  \\Illuminate\\Pagination\\Cursor|string|null  $cursor
 * @return \\Illuminate\\Contracts\\Pagination\\CursorPaginator
 */',
        'startLine' => 3477,
        'endLine' => 3480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ensureOrderForCursorPagination' => 
      array (
        'name' => 'ensureOrderForCursorPagination',
        'parameters' => 
        array (
          'shouldReverse' => 
          array (
            'name' => 'shouldReverse',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 3488,
                'endLine' => 3488,
                'startTokenPos' => 14555,
                'startFilePos' => 106234,
                'endTokenPos' => 14555,
                'endFilePos' => 106238,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3488,
            'endLine' => 3488,
            'startColumn' => 55,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the proper order by required for cursor pagination.
 *
 * @param  bool  $shouldReverse
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3488,
        'endLine' => 3514,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getCountForPagination' => 
      array (
        'name' => 'getCountForPagination',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3522,
                'endLine' => 3522,
                'startTokenPos' => 14802,
                'startFilePos' => 107342,
                'endTokenPos' => 14804,
                'endFilePos' => 107346,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3522,
            'endLine' => 3522,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the count of the total records for the paginator.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return int<0, max>
 */',
        'startLine' => 3522,
        'endLine' => 3536,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'runPaginationCountQuery' => 
      array (
        'name' => 'runPaginationCountQuery',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3544,
                'endLine' => 3544,
                'startTokenPos' => 14910,
                'startFilePos' => 108165,
                'endTokenPos' => 14912,
                'endFilePos' => 108169,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3544,
            'endLine' => 3544,
            'startColumn' => 48,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a pagination count query.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return array<mixed>
 */',
        'startLine' => 3544,
        'endLine' => 3566,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneForPaginationCount' => 
      array (
        'name' => 'cloneForPaginationCount',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the existing query instance for usage in a pagination subquery.
 *
 * @return self
 */',
        'startLine' => 3573,
        'endLine' => 3577,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'withoutSelectAliases' => 
      array (
        'name' => 'withoutSelectAliases',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3585,
            'endLine' => 3585,
            'startColumn' => 45,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the column aliases since they will break count queries.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>
 */',
        'startLine' => 3585,
        'endLine' => 3592,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cursor' => 
      array (
        'name' => 'cursor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a lazy collection for the given query.
 *
 * @return \\Illuminate\\Support\\LazyCollection<int, \\stdClass>
 */',
        'startLine' => 3599,
        'endLine' => 3612,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => true,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'enforceOrderBy' => 
      array (
        'name' => 'enforceOrderBy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Throw an exception if the query doesn\'t have an orderBy clause.
 *
 * @return void
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 3621,
        'endLine' => 3626,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluck' => 
      array (
        'name' => 'pluck',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3635,
            'endLine' => 3635,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3635,
                'endLine' => 3635,
                'startTokenPos' => 15469,
                'startFilePos' => 111288,
                'endTokenPos' => 15469,
                'endFilePos' => 111291,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3635,
            'endLine' => 3635,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a collection instance containing the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string|null  $key
 * @return \\Illuminate\\Support\\Collection<array-key, mixed>
 */',
        'startLine' => 3635,
        'endLine' => 3665,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'stripTableForPluck' => 
      array (
        'name' => 'stripTableForPluck',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3673,
            'endLine' => 3673,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Strip off the table name or alias from a column identifier.
 *
 * @param  string  $column
 * @return string|null
 */',
        'startLine' => 3673,
        'endLine' => 3686,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluckFromObjectColumn' => 
      array (
        'name' => 'pluckFromObjectColumn',
        'parameters' => 
        array (
          'queryResult' => 
          array (
            'name' => 'queryResult',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3696,
            'endLine' => 3696,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3696,
            'endLine' => 3696,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3696,
            'endLine' => 3696,
            'startColumn' => 69,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve column values from rows represented as objects.
 *
 * @param  array  $queryResult
 * @param  string  $column
 * @param  string  $key
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3696,
        'endLine' => 3711,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluckFromArrayColumn' => 
      array (
        'name' => 'pluckFromArrayColumn',
        'parameters' => 
        array (
          'queryResult' => 
          array (
            'name' => 'queryResult',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3721,
            'endLine' => 3721,
            'startColumn' => 45,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3721,
            'endLine' => 3721,
            'startColumn' => 59,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3721,
            'endLine' => 3721,
            'startColumn' => 68,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve column values from rows represented as arrays.
 *
 * @param  array  $queryResult
 * @param  string  $column
 * @param  string  $key
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3721,
        'endLine' => 3736,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'implode' => 
      array (
        'name' => 'implode',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3745,
            'endLine' => 3745,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'glue' => 
          array (
            'name' => 'glue',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 3745,
                'endLine' => 3745,
                'startTokenPos' => 15992,
                'startFilePos' => 114613,
                'endTokenPos' => 15992,
                'endFilePos' => 114614,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3745,
            'endLine' => 3745,
            'startColumn' => 38,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Concatenate values of a given column as a string.
 *
 * @param  string  $column
 * @param  string  $glue
 * @return string
 */',
        'startLine' => 3745,
        'endLine' => 3748,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'exists' => 
      array (
        'name' => 'exists',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if any rows exist for the current query.
 *
 * @return bool
 */',
        'startLine' => 3755,
        'endLine' => 3773,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'doesntExist' => 
      array (
        'name' => 'doesntExist',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if no rows exist for the current query.
 *
 * @return bool
 */',
        'startLine' => 3780,
        'endLine' => 3783,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'existsOr' => 
      array (
        'name' => 'existsOr',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3790,
            'endLine' => 3790,
            'startColumn' => 30,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback if no rows exist for the current query.
 *
 * @return mixed
 */',
        'startLine' => 3790,
        'endLine' => 3793,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'doesntExistOr' => 
      array (
        'name' => 'doesntExistOr',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3800,
            'endLine' => 3800,
            'startColumn' => 35,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback if rows exist for the current query.
 *
 * @return mixed
 */',
        'startLine' => 3800,
        'endLine' => 3803,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'count' => 
      array (
        'name' => 'count',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '\'*\'',
              'attributes' => 
              array (
                'startLine' => 3811,
                'endLine' => 3811,
                'startTokenPos' => 16230,
                'startFilePos' => 116314,
                'endTokenPos' => 16230,
                'endFilePos' => 116316,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3811,
            'endLine' => 3811,
            'startColumn' => 27,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the "count" result of the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $columns
 * @return int<0, max>
 */',
        'startLine' => 3811,
        'endLine' => 3814,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'min' => 
      array (
        'name' => 'min',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3822,
            'endLine' => 3822,
            'startColumn' => 25,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the minimum value of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 3822,
        'endLine' => 3825,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'max' => 
      array (
        'name' => 'max',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3833,
            'endLine' => 3833,
            'startColumn' => 25,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the maximum value of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 3833,
        'endLine' => 3836,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'sum' => 
      array (
        'name' => 'sum',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3844,
            'endLine' => 3844,
            'startColumn' => 25,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the sum of the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 3844,
        'endLine' => 3849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'avg' => 
      array (
        'name' => 'avg',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3857,
            'endLine' => 3857,
            'startColumn' => 25,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the average of the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 3857,
        'endLine' => 3860,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'average' => 
      array (
        'name' => 'average',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3868,
            'endLine' => 3868,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Alias for the "avg" method.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 3868,
        'endLine' => 3871,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'aggregate' => 
      array (
        'name' => 'aggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3880,
            'endLine' => 3880,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3880,
                'endLine' => 3880,
                'startTokenPos' => 16429,
                'startFilePos' => 118029,
                'endTokenPos' => 16431,
                'endFilePos' => 118033,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3880,
            'endLine' => 3880,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute an aggregate function on the database.
 *
 * @param  string  $function
 * @param  array  $columns
 * @return mixed
 */',
        'startLine' => 3880,
        'endLine' => 3890,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'numericAggregate' => 
      array (
        'name' => 'numericAggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3899,
            'endLine' => 3899,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3899,
                'endLine' => 3899,
                'startTokenPos' => 16556,
                'startFilePos' => 118659,
                'endTokenPos' => 16558,
                'endFilePos' => 118663,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3899,
            'endLine' => 3899,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute a numeric aggregate function on the database.
 *
 * @param  string  $function
 * @param  array  $columns
 * @return float|int
 */',
        'startLine' => 3899,
        'endLine' => 3920,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'setAggregate' => 
      array (
        'name' => 'setAggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3929,
            'endLine' => 3929,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3929,
            'endLine' => 3929,
            'startColumn' => 48,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the aggregate property without running the query.
 *
 * @param  string  $function
 * @param  array<\\Illuminate\\Contracts\\Database\\Query\\Expression|string>  $columns
 * @return $this
 */',
        'startLine' => 3929,
        'endLine' => 3940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'onceWithColumns' => 
      array (
        'name' => 'onceWithColumns',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3953,
            'endLine' => 3953,
            'startColumn' => 40,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3953,
            'endLine' => 3953,
            'startColumn' => 50,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback while selecting the given columns.
 *
 * After running the callback, the columns are reset to the original value.
 *
 * @template TResult
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  callable(): TResult  $callback
 * @return TResult
 */',
        'startLine' => 3953,
        'endLine' => 3966,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insert' => 
      array (
        'name' => 'insert',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3973,
            'endLine' => 3973,
            'startColumn' => 28,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert new records into the database.
 *
 * @return bool
 */',
        'startLine' => 3973,
        'endLine' => 4006,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertOrIgnore' => 
      array (
        'name' => 'insertOrIgnore',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4013,
            'endLine' => 4013,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert new records into the database while ignoring errors.
 *
 * @return int<0, max>
 */',
        'startLine' => 4013,
        'endLine' => 4035,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertGetId' => 
      array (
        'name' => 'insertGetId',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4043,
            'endLine' => 4043,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sequence' => 
          array (
            'name' => 'sequence',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4043,
                'endLine' => 4043,
                'startTokenPos' => 17141,
                'startFilePos' => 123041,
                'endTokenPos' => 17141,
                'endFilePos' => 123044,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4043,
            'endLine' => 4043,
            'startColumn' => 48,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert a new record and get the value of the primary key.
 *
 * @param  string|null  $sequence
 * @return int
 */',
        'startLine' => 4043,
        'endLine' => 4052,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertUsing' => 
      array (
        'name' => 'insertUsing',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4060,
            'endLine' => 4060,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4060,
            'endLine' => 4060,
            'startColumn' => 49,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert new records into the table using a subquery.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return int
 */',
        'startLine' => 4060,
        'endLine' => 4070,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertOrIgnoreUsing' => 
      array (
        'name' => 'insertOrIgnoreUsing',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4078,
            'endLine' => 4078,
            'startColumn' => 41,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4078,
            'endLine' => 4078,
            'startColumn' => 57,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert new records into the table using a subquery while ignoring errors.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return int
 */',
        'startLine' => 4078,
        'endLine' => 4088,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'update' => 
      array (
        'name' => 'update',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4095,
            'endLine' => 4095,
            'startColumn' => 28,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Update records in the database.
 *
 * @return int<0, max>
 */',
        'startLine' => 4095,
        'endLine' => 4118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'updateFrom' => 
      array (
        'name' => 'updateFrom',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4125,
            'endLine' => 4125,
            'startColumn' => 32,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Update records in a PostgreSQL database using the update from syntax.
 *
 * @return int
 */',
        'startLine' => 4125,
        'endLine' => 4138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'updateOrInsert' => 
      array (
        'name' => 'updateOrInsert',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4145,
            'endLine' => 4145,
            'startColumn' => 36,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4145,
                'endLine' => 4145,
                'startTokenPos' => 17765,
                'startFilePos' => 126399,
                'endTokenPos' => 17766,
                'endFilePos' => 126400,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4145,
            'endLine' => 4145,
            'startColumn' => 55,
            'endColumn' => 81,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert or update a record matching the attributes, and fill it with values.
 *
 * @return bool
 */',
        'startLine' => 4145,
        'endLine' => 4162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'upsert' => 
      array (
        'name' => 'upsert',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4169,
            'endLine' => 4169,
            'startColumn' => 28,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'uniqueBy' => 
          array (
            'name' => 'uniqueBy',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4169,
            'endLine' => 4169,
            'startColumn' => 43,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'update' => 
          array (
            'name' => 'update',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4169,
                'endLine' => 4169,
                'startTokenPos' => 17903,
                'startFilePos' => 126972,
                'endTokenPos' => 17903,
                'endFilePos' => 126975,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4169,
            'endLine' => 4169,
            'startColumn' => 67,
            'endColumn' => 87,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert new records or update the existing ones.
 *
 * @return int
 */',
        'startLine' => 4169,
        'endLine' => 4204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'increment' => 
      array (
        'name' => 'increment',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4215,
            'endLine' => 4215,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 4215,
                'endLine' => 4215,
                'startTokenPos' => 18165,
                'startFilePos' => 128194,
                'endTokenPos' => 18165,
                'endFilePos' => 128194,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4215,
            'endLine' => 4215,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4215,
                'endLine' => 4215,
                'startTokenPos' => 18174,
                'startFilePos' => 128212,
                'endTokenPos' => 18175,
                'endFilePos' => 128213,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4215,
            'endLine' => 4215,
            'startColumn' => 53,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Increment a column\'s value by a given amount.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4215,
        'endLine' => 4222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'incrementEach' => 
      array (
        'name' => 'incrementEach',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4233,
            'endLine' => 4233,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4233,
                'endLine' => 4233,
                'startTokenPos' => 18245,
                'startFilePos' => 128778,
                'endTokenPos' => 18246,
                'endFilePos' => 128779,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4233,
            'endLine' => 4233,
            'startColumn' => 51,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Increment the given column\'s values by the given amounts.
 *
 * @param  array<string, float|int|numeric-string>  $columns
 * @param  array<string, mixed>  $extra
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4233,
        'endLine' => 4246,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'decrement' => 
      array (
        'name' => 'decrement',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4257,
            'endLine' => 4257,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 4257,
                'endLine' => 4257,
                'startTokenPos' => 18384,
                'startFilePos' => 129606,
                'endTokenPos' => 18384,
                'endFilePos' => 129606,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4257,
            'endLine' => 4257,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4257,
                'endLine' => 4257,
                'startTokenPos' => 18393,
                'startFilePos' => 129624,
                'endTokenPos' => 18394,
                'endFilePos' => 129625,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4257,
            'endLine' => 4257,
            'startColumn' => 53,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Decrement a column\'s value by a given amount.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4257,
        'endLine' => 4264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'decrementEach' => 
      array (
        'name' => 'decrementEach',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4275,
            'endLine' => 4275,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4275,
                'endLine' => 4275,
                'startTokenPos' => 18464,
                'startFilePos' => 130190,
                'endTokenPos' => 18465,
                'endFilePos' => 130191,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4275,
            'endLine' => 4275,
            'startColumn' => 51,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Decrement the given column\'s values by the given amounts.
 *
 * @param  array<string, float|int|numeric-string>  $columns
 * @param  array<string, mixed>  $extra
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4275,
        'endLine' => 4288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4296,
                'endLine' => 4296,
                'startTokenPos' => 18600,
                'startFilePos' => 130895,
                'endTokenPos' => 18600,
                'endFilePos' => 130898,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4296,
            'endLine' => 4296,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete records from the database.
 *
 * @param  mixed  $id
 * @return int
 */',
        'startLine' => 4296,
        'endLine' => 4312,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'truncate' => 
      array (
        'name' => 'truncate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a "truncate" statement on the table.
 *
 * @return void
 */',
        'startLine' => 4319,
        'endLine' => 4326,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'newQuery' => 
      array (
        'name' => 'newQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new instance of the query builder.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 4333,
        'endLine' => 4336,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forSubQuery' => 
      array (
        'name' => 'forSubQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new query instance for a sub-query.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 4343,
        'endLine' => 4346,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getColumns' => 
      array (
        'name' => 'getColumns',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the query builder\'s columns in a text-only array with all expressions evaluated.
 *
 * @return list<string>
 */',
        'startLine' => 4353,
        'endLine' => 4358,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4366,
            'endLine' => 4366,
            'startColumn' => 25,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a raw database expression.
 *
 * @param  mixed  $value
 * @return \\Illuminate\\Contracts\\Database\\Query\\Expression
 */',
        'startLine' => 4366,
        'endLine' => 4369,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getUnionBuilders' => 
      array (
        'name' => 'getUnionBuilders',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query builder instances that are used in the union of the query.
 *
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 4376,
        'endLine' => 4381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getLimit' => 
      array (
        'name' => 'getLimit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the "limit" value for the query or null if it\'s not set.
 *
 * @return mixed
 */',
        'startLine' => 4388,
        'endLine' => 4393,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getOffset' => 
      array (
        'name' => 'getOffset',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the "offset" value for the query or null if it\'s not set.
 *
 * @return mixed
 */',
        'startLine' => 4400,
        'endLine' => 4405,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getBindings' => 
      array (
        'name' => 'getBindings',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current query value bindings in a flattened array.
 *
 * @return list<mixed>
 */',
        'startLine' => 4412,
        'endLine' => 4415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getRawBindings' => 
      array (
        'name' => 'getRawBindings',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the raw array of bindings.
 *
 * @return array{
 *      select: list<mixed>,
 *      from: list<mixed>,
 *      join: list<mixed>,
 *      where: list<mixed>,
 *      groupBy: list<mixed>,
 *      having: list<mixed>,
 *      order: list<mixed>,
 *      union: list<mixed>,
 *      unionOrder: list<mixed>,
 * }
 */',
        'startLine' => 4432,
        'endLine' => 4435,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'setBindings' => 
      array (
        'name' => 'setBindings',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4446,
            'endLine' => 4446,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 4446,
                'endLine' => 4446,
                'startTokenPos' => 19118,
                'startFilePos' => 134806,
                'endTokenPos' => 19118,
                'endFilePos' => 134812,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4446,
            'endLine' => 4446,
            'startColumn' => 50,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the bindings on the query builder.
 *
 * @param  list<mixed>  $bindings
 * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4446,
        'endLine' => 4455,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addBinding' => 
      array (
        'name' => 'addBinding',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4466,
            'endLine' => 4466,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 4466,
                'endLine' => 4466,
                'startTokenPos' => 19193,
                'startFilePos' => 135350,
                'endTokenPos' => 19193,
                'endFilePos' => 135356,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4466,
            'endLine' => 4466,
            'startColumn' => 40,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a binding to the query.
 *
 * @param  mixed  $value
 * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4466,
        'endLine' => 4482,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'castBinding' => 
      array (
        'name' => 'castBinding',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4490,
            'endLine' => 4490,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Cast the given binding value.
 *
 * @param  mixed  $value
 * @return mixed
 */',
        'startLine' => 4490,
        'endLine' => 4497,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'mergeBindings' => 
      array (
        'name' => 'mergeBindings',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4505,
            'endLine' => 4505,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Merge an array of bindings into our bindings.
 *
 * @param  self  $query
 * @return $this
 */',
        'startLine' => 4505,
        'endLine' => 4510,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cleanBindings' => 
      array (
        'name' => 'cleanBindings',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4518,
            'endLine' => 4518,
            'startColumn' => 35,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove all of the expressions from a list of bindings.
 *
 * @param  array<mixed>  $bindings
 * @return list<mixed>
 */',
        'startLine' => 4518,
        'endLine' => 4527,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'flattenValue' => 
      array (
        'name' => 'flattenValue',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4535,
            'endLine' => 4535,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a scalar type value from an unknown type of input.
 *
 * @param  mixed  $value
 * @return mixed
 */',
        'startLine' => 4535,
        'endLine' => 4538,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'defaultKeyName' => 
      array (
        'name' => 'defaultKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default key name of the table.
 *
 * @return string
 */',
        'startLine' => 4545,
        'endLine' => 4548,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getConnection' => 
      array (
        'name' => 'getConnection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database connection instance.
 *
 * @return \\Illuminate\\Database\\ConnectionInterface
 */',
        'startLine' => 4555,
        'endLine' => 4558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ensureConnectionSupportsVectors' => 
      array (
        'name' => 'ensureConnectionSupportsVectors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the database connection supports vector queries.
 *
 * @return void
 */',
        'startLine' => 4565,
        'endLine' => 4570,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getProcessor' => 
      array (
        'name' => 'getProcessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database query processor instance.
 *
 * @return \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'startLine' => 4577,
        'endLine' => 4580,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getGrammar' => 
      array (
        'name' => 'getGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query grammar instance.
 *
 * @return \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'startLine' => 4587,
        'endLine' => 4590,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'useWritePdo' => 
      array (
        'name' => 'useWritePdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Use the "write" PDO connection when executing the query.
 *
 * @return $this
 */',
        'startLine' => 4597,
        'endLine' => 4602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'isQueryable' => 
      array (
        'name' => 'isQueryable',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4610,
            'endLine' => 4610,
            'startColumn' => 36,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the value is a query builder instance or a Closure.
 *
 * @param  mixed  $value
 * @return bool
 */',
        'startLine' => 4610,
        'endLine' => 4616,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'clone' => 
      array (
        'name' => 'clone',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the query.
 *
 * @return static
 */',
        'startLine' => 4623,
        'endLine' => 4626,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneWithout' => 
      array (
        'name' => 'cloneWithout',
        'parameters' => 
        array (
          'properties' => 
          array (
            'name' => 'properties',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4633,
            'endLine' => 4633,
            'startColumn' => 34,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the query without the given properties.
 *
 * @return static
 */',
        'startLine' => 4633,
        'endLine' => 4640,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneWithoutBindings' => 
      array (
        'name' => 'cloneWithoutBindings',
        'parameters' => 
        array (
          'except' => 
          array (
            'name' => 'except',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4647,
            'endLine' => 4647,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the query without the given bindings.
 *
 * @return static
 */',
        'startLine' => 4647,
        'endLine' => 4654,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dump' => 
      array (
        'name' => 'dump',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4662,
            'endLine' => 4662,
            'startColumn' => 26,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the current SQL and bindings.
 *
 * @param  mixed  ...$args
 * @return $this
 */',
        'startLine' => 4662,
        'endLine' => 4671,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dumpRawSql' => 
      array (
        'name' => 'dumpRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the raw current SQL with embedded bindings.
 *
 * @return $this
 */',
        'startLine' => 4678,
        'endLine' => 4683,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dd' => 
      array (
        'name' => 'dd',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Die and dump the current SQL and bindings.
 *
 * @return never
 */',
        'startLine' => 4690,
        'endLine' => 4693,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ddRawSql' => 
      array (
        'name' => 'ddRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Die and dump the current SQL with embedded bindings.
 *
 * @return never
 */',
        'startLine' => 4700,
        'endLine' => 4703,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4714,
            'endLine' => 4714,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4714,
            'endLine' => 4714,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle dynamic method calls into the method.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 *
 * @throws \\BadMethodCallException
 */',
        'startLine' => 4714,
        'endLine' => 4725,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\buildswheredateclauses::__call',
          ),
        ),
        'Illuminate\\Database\\Concerns\\BuildsQueries' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\buildsqueries::__call',
          ),
        ),
        'Illuminate\\Database\\Concerns\\ExplainsQueries' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\explainsqueries::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\ForwardsCalls' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\forwardscalls::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\database\\concerns\\buildswheredateclauses::__call' => 'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses::__call',
        'illuminate\\database\\concerns\\buildsqueries::__call' => 'Illuminate\\Database\\Concerns\\BuildsQueries::__call',
        'illuminate\\database\\concerns\\explainsqueries::__call' => 'Illuminate\\Database\\Concerns\\ExplainsQueries::__call',
        'illuminate\\support\\traits\\forwardscalls::__call' => 'Illuminate\\Support\\Traits\\ForwardsCalls::__call',
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));
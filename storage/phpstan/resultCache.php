<?php declare(strict_types = 1);

return [
	'lastFullAnalysisTime' => 1778207958,
	'meta' => array (
  'cacheVersion' => 'v12-linesToIgnore',
  'phpstanVersion' => '2.1.46',
  'fnsr' => false,
  'metaExtensions' => 
  array (
  ),
  'phpVersion' => 80400,
  'projectConfig' => '{conditionalTags: {Larastan\\Larastan\\Rules\\NoEnvCallsOutsideOfConfigRule: {phpstan.rules.rule: %noEnvCallsOutsideOfConfig%}, Larastan\\Larastan\\Rules\\NoModelMakeRule: {phpstan.rules.rule: %noModelMake%}, Larastan\\Larastan\\Rules\\NoUnnecessaryCollectionCallRule: {phpstan.rules.rule: %noUnnecessaryCollectionCall%}, Larastan\\Larastan\\Rules\\NoUnnecessaryEnumerableToArrayCallsRule: {phpstan.rules.rule: %noUnnecessaryEnumerableToArrayCalls%}, Larastan\\Larastan\\Rules\\OctaneCompatibilityRule: {phpstan.rules.rule: %checkOctaneCompatibility%}, Larastan\\Larastan\\Rules\\UnusedViewsRule: {phpstan.rules.rule: %checkUnusedViews%}, Larastan\\Larastan\\Rules\\NoMissingTranslationsRule: {phpstan.rules.rule: %checkMissingTranslations%}, Larastan\\Larastan\\Rules\\ModelAppendsRule: {phpstan.rules.rule: %checkModelAppends%}, Larastan\\Larastan\\Rules\\NoPublicModelScopeAndAccessorRule: {phpstan.rules.rule: %checkModelMethodVisibility%}, Larastan\\Larastan\\Rules\\NoAuthFacadeInRequestScopeRule: {phpstan.rules.rule: %checkAuthCallsWhenInRequestScope%}, Larastan\\Larastan\\Rules\\NoAuthHelperInRequestScopeRule: {phpstan.rules.rule: %checkAuthCallsWhenInRequestScope%}, Larastan\\Larastan\\ReturnTypes\\Helpers\\EnvFunctionDynamicFunctionReturnTypeExtension: {phpstan.broker.dynamicFunctionReturnTypeExtension: %generalizeEnvReturnType%}, Larastan\\Larastan\\ReturnTypes\\Helpers\\ConfigFunctionDynamicFunctionReturnTypeExtension: {phpstan.broker.dynamicFunctionReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\ReturnTypes\\ConfigRepositoryDynamicMethodReturnTypeExtension: {phpstan.broker.dynamicMethodReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\ReturnTypes\\ConfigFacadeCollectionDynamicStaticMethodReturnTypeExtension: {phpstan.broker.dynamicStaticMethodReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\Rules\\ConfigCollectionRule: {phpstan.rules.rule: %checkConfigTypes%}}, parameters: {universalObjectCratesClasses: [Illuminate\\Http\\Request, Illuminate\\Support\\Optional], earlyTerminatingFunctionCalls: [abort, dd], mixinExcludeClasses: [Eloquent], bootstrapFiles: [bootstrap.php, tools/phpstan/bootstrap.php], checkOctaneCompatibility: false, noEnvCallsOutsideOfConfig: true, noModelMake: true, noUnnecessaryCollectionCall: true, noUnnecessaryCollectionCallOnly: [], noUnnecessaryCollectionCallExcept: [], noUnnecessaryEnumerableToArrayCalls: false, squashedMigrationsPath: [], databaseMigrationsPath: [], disableMigrationScan: false, disableSchemaScan: false, configDirectories: [], viewDirectories: [], translationDirectories: [], checkModelProperties: false, checkUnusedViews: false, checkMissingTranslations: false, checkModelAppends: true, checkModelMethodVisibility: false, generalizeEnvReturnType: false, checkConfigTypes: false, checkAuthCallsWhenInRequestScope: false, parseModelCastsMethod: false, enableMigrationCache: false, paths: [C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app, C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests], level: 0, tmpDir: C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\storage\\phpstan}, rules: [Larastan\\Larastan\\Rules\\UselessConstructs\\NoUselessWithFunctionCallsRule, Larastan\\Larastan\\Rules\\UselessConstructs\\NoUselessValueFunctionCallsRule, Larastan\\Larastan\\Rules\\DeferrableServiceProviderMissingProvidesRule, Larastan\\Larastan\\Rules\\ConsoleCommand\\UndefinedArgumentOrOptionRule], services: {{class: Larastan\\Larastan\\Methods\\RelationForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ModelForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\EloquentBuilderForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\HigherOrderTapProxyExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\HigherOrderCollectionProxyExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\StorageMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\Extension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ModelFactoryMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\RedirectResponseMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\MacroMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ViewWithMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\ModelAccessorExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\ModelPropertyExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\HigherOrderCollectionProxyPropertyExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\HigherOrderTapProxyExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Contracts\\Container\\Container}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Container\\Container}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Contracts\\Foundation\\Application}}, {class: Larastan\\Larastan\\Properties\\ModelRelationsExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelOnlyDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelFactoryDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AuthExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\GuardDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AuthManagerExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\DateExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\GuardExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestFileExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestRouteExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestUserExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\EloquentBuilderExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RelationCollectionExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TestCaseExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Support\\CollectionHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\AuthExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\CollectExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\NowAndTodayExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ResponseExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ValidatorExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\LiteralExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\CollectionFilterRejectDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\CollectionWhereNotNullDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\NewModelQueryDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\FactoryDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: abort, negate: false}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: abort, negate: true}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: throw, negate: false}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: throw, negate: true}}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\AppExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ValueExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\StrExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\TapExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\StorageDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\GenericEloquentCollectionTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Types\\ViewStringTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Rules\\OctaneCompatibilityRule}, {class: Larastan\\Larastan\\Rules\\NoEnvCallsOutsideOfConfigRule, arguments: {configDirectories: %configDirectories%}}, {class: Larastan\\Larastan\\Rules\\NoModelMakeRule}, {class: Larastan\\Larastan\\Rules\\NoUnnecessaryCollectionCallRule, arguments: {onlyMethods: %noUnnecessaryCollectionCallOnly%, excludeMethods: %noUnnecessaryCollectionCallExcept%}}, {class: Larastan\\Larastan\\Rules\\NoUnnecessaryEnumerableToArrayCallsRule}, {class: Larastan\\Larastan\\Rules\\ModelAppendsRule}, {class: Larastan\\Larastan\\Rules\\NoPublicModelScopeAndAccessorRule}, {class: Larastan\\Larastan\\Types\\GenericEloquentBuilderTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {class: Illuminate\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\AppEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {class: Illuminate\\Contracts\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\AppFacadeEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\ModelProperty\\ModelPropertyTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension], arguments: {active: %checkModelProperties%}}, {class: Larastan\\Larastan\\Types\\CollectionOf\\CollectionOfTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Properties\\MigrationHelper, arguments: {databaseMigrationPath: %databaseMigrationsPath%, disableMigrationScan: %disableMigrationScan%, parser: @migrationsParser, reflectionProvider: @reflectionProvider}}, iamcalSqlParser: {class: Larastan\\Larastan\\SQL\\IamcalSqlParser, autowired: false}, sqlParserFactory: {class: Larastan\\Larastan\\SQL\\SqlParserFactory, arguments: {iamcalSqlParser: @iamcalSqlParser}}, sqlParser: {type: Larastan\\Larastan\\SQL\\SqlParser, factory: [@sqlParserFactory, create]}, {class: Larastan\\Larastan\\Properties\\SquashedMigrationHelper, arguments: {schemaPaths: %squashedMigrationsPath%, disableSchemaScan: %disableSchemaScan%}}, {class: Larastan\\Larastan\\Properties\\ModelCastHelper, arguments: {parser: @currentPhpVersionSimpleDirectParser, parseModelCastsMethod: %parseModelCastsMethod%}}, {class: Larastan\\Larastan\\Properties\\MigrationCache, arguments: {cacheDirectory: %tmpDir%, enabled: %enableMigrationCache%}}, {class: Larastan\\Larastan\\Properties\\ModelPropertyHelper}, {class: Larastan\\Larastan\\Rules\\ModelRuleHelper}, {class: Larastan\\Larastan\\Methods\\BuilderHelper, arguments: {checkProperties: %checkModelProperties%}}, {class: Larastan\\Larastan\\Rules\\RelationExistenceRule, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Rules\\CheckDispatchArgumentTypesCompatibleWithClassConstructorRule, arguments: {dispatchableClass: Illuminate\\Foundation\\Bus\\Dispatchable}, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Rules\\CheckDispatchArgumentTypesCompatibleWithClassConstructorRule, arguments: {dispatchableClass: Illuminate\\Foundation\\Events\\Dispatchable}, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Properties\\Schema\\MySqlDataTypeToPhpTypeConverter}, {class: Larastan\\Larastan\\LarastanStubFilesExtension, tags: [phpstan.stubFilesExtension]}, {class: Larastan\\Larastan\\Rules\\UnusedViewsRule}, {class: Larastan\\Larastan\\Collectors\\UsedViewFunctionCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedEmailViewCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewMakeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewFacadeMakeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedRouteFacadeViewCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewInAnotherViewCollector}, {class: Larastan\\Larastan\\Support\\ViewFileHelper, arguments: {viewDirectories: %viewDirectories%}}, {class: Larastan\\Larastan\\Support\\ViewParser, arguments: {parser: @currentPhpVersionSimpleDirectParser}}, {class: Larastan\\Larastan\\Rules\\NoMissingTranslationsRule, arguments: {translationDirectories: %translationDirectories%}}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationFunctionCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationTranslatorCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationFacadeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationViewCollector}, {class: Larastan\\Larastan\\ReturnTypes\\ApplicationMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\ArgumentDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\HasArgumentDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\OptionDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\HasOptionDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TranslatorGetReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\LangGetReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TransHelperReturnTypeExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\DoubleUnderscoreHelperReturnTypeExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppMakeHelper}, {class: Larastan\\Larastan\\Internal\\ConsoleApplicationResolver}, {class: Larastan\\Larastan\\Internal\\ConsoleApplicationHelper}, {class: Larastan\\Larastan\\Support\\HigherOrderCollectionProxyHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ConfigFunctionDynamicFunctionReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\ConfigRepositoryDynamicMethodReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\ConfigFacadeCollectionDynamicStaticMethodReturnTypeExtension}, {class: Larastan\\Larastan\\Support\\ConfigParser, arguments: {parser: @currentPhpVersionSimpleDirectParser, configPaths: %configDirectories%, treatPhpDocTypesAsCertain: %treatPhpDocTypesAsCertain%}}, {class: Larastan\\Larastan\\Internal\\ConfigHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\EnvFunctionDynamicFunctionReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\FormRequestSafeDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Rules\\NoAuthFacadeInRequestScopeRule}, {class: Larastan\\Larastan\\Rules\\NoAuthHelperInRequestScopeRule}, {class: Larastan\\Larastan\\Rules\\ConfigCollectionRule}, {class: Illuminate\\Filesystem\\Filesystem, autowired: self}, migrationsParser: {class: PHPStan\\Parser\\CachedParser, arguments: {originalParser: @currentPhpVersionSimpleDirectParser, cachedNodesByStringCountMax: %cache.nodesByStringCountMax%}, autowired: false}}}',
  'analysedPaths' => 
  array (
    0 => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList',
  ),
  'scannedFiles' => 
  array (
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Casts\\LenientDepositStatusCast.php' => 'cdd182d52970ab0fa9f430900e31df6629a490927a11c06cc62d76d112cef7d4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Casts\\ReservationOrderTypeCast.php' => 'e5d16036c03f49f2923cf00dbbec0c143ef91f63eb3a6fa50af64e332348cb3f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ConversationChannel.php' => 'a97105449c115878287f0ba5bc577920bf874f62f97aec371b4780510e24fe11',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ConversationStatus.php' => '8e4d96167a3459fe5a55cf0353decd74ca83b823e74fb8417536ee115ef85f5e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\DepositStatus.php' => '7a6529d4035c30040240410683de08a4e0cc1fd0a6dbb9d5d667c9fd31ff6738',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\KitchenStationOutputMode.php' => '55830fcdd40a672cc6118d830800b8c010ada0b64198d794d9d65f7f3a344143',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\KitchenTicketStatus.php' => '3b10d886c73cfe479449895c48718438c0ede667c6521a4a8ae04b679978d2ed',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\MessageSender.php' => 'dde434dc6602cdc24c1783cec0b6f62ad7f13f6e5a1ebd809e1d2368783d2fbb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\MessageType.php' => '529ec7df8a4a164806b755c983e0318725d6af9f0ac3df37456371cba47ae141',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\PaymentProviderWebhookReceiptStatus.php' => '1be328d5b1157beec009b018e629254d0a3e38626aa14f8a8bd88d373982b8f9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\PaymentSessionScope.php' => '71f1ef7c63f80bde465f5815b04b66ea3d429907d48a69dddd44bb265b9dfa6c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\PaymentStatus.php' => 'd04c73ee2e8c2cd987e8b9ab9f58b758ccde1b6ac43b17b642c1d70438e9ffc9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\PurchaseOrderStatus.php' => '0827d34a2e2d972dd2f4fad7912f6068237527c50e476fa091fd0051b675e870',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\PurchaseReceiptStatus.php' => '27861d3f02cbac1701d95935009edb14fb2a90b91ebbd28f32c746322cb6fd93',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationBillPaymentSessionStatus.php' => '379af75e201695e31a182bdcaa5e193d2a6a0acbc311036f2cfc84a6586d4538',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationBillPaymentSettlementStatus.php' => '2391f4dab61c7a63091c3be0deb5e8e5499317d19ceb470913ae81d1e5557efb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationDepositIntentStatus.php' => '78858e388882803513183b754b9e129a527166be3b79d77ea183353362e88782',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationDepositPaymentSessionStatus.php' => '8ed13d8216058bcb7448097ff2ca2a95366e58b3376d778f2b93a4a7b3beb7a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationDepositPaymentSettlementStatus.php' => '967dbe5d1b418a9bca1dbfa83df60d29d09c3d816613052b76f13396e9c7987c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationOrderItemStatus.php' => '333e5e28b86444c1814af4e812bba6b89e5fb9dcff950a567d23472e1ed0e61b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationOrderStatus.php' => '2d36dd1a1748ff0e428547a4eaef31a00b029dbd8642d96423de96bf522e45e0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationOrderType.php' => '5b8c6904c074ca1192ea81c405c5d36c1e4c51eb0554ea402c9fe211ca0b9921',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\ReservationStatus.php' => 'cb3134422e0ff4ec02ab5b4fc89233699e4c6589d3b36dbcd101eef436e4674c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\RestaurantTableStatus.php' => 'c5b866818a1bd15b1e048bea7dec51eba5e250e4f7979ccb6764e20350c2fef3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\StaffConversationWorkflowState.php' => 'b16a278a41b63de341803eca259cc29075899fefcef5ebc7b54b289240f62365',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\TableHoldStatus.php' => 'c560faae4b5f5f6d79d69e42a46e13ad4efbc5400e51a84b47244defbfca5458',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\VoucherDiscountType.php' => '08e35c0ce2ab501cea82a964fdb1a5bb38e7804a0c3aee2690dfe473a3c0bbfb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\WaitingListCustomerResponseState.php' => '44d6cd54e7f4bb148d89d52ec20fbcbaf5f394e121e30710727022405e01cd46',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\WaitingListCustomerResponseStatus.php' => '77b94a9a9e8567323aa6342c98f5edcbcf59cef5809a62faf5d06c759d126d31',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Enums\\WaitingListStatus.php' => '2a232caeb3d649e9164f93f9d84aaac2dc7d75a230bc8210c52e7b495beeb67f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Concerns\\AppliesDeprecatedRouteHeaders.php' => '07f277ad5405d10d86aaf4659afa93b6a8b25dff8a4e13a2c93b6ef99f1b99a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Concerns\\AuthorizesResolvedStaffCapability.php' => 'e1c6116429f6cdb0fb5bc027e584085d7310c5bbd14e3facf98a88873354fd4e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Concerns\\MapsFrameworkValidationFailure.php' => '7b8801059d18ba6a44c837b2c919bff5e7cb4f910b2e7c97cea38f6d20b4cbc5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Concerns\\ResolvesStaffActor.php' => '8538840a8f06a43fb2ce333b231bd6a0bb29289b866266c6b0f3455739580023',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Concerns\\RespondsWithCustomerReservationNotFound.php' => '9920e845e212f185c51b1cbff5de8765926262ebb0838e37962c5459b9ec01a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Controllers\\Controller.php' => '25d1c1ef8e6cc8a376553faacfba2b07d9dfaee9bdbb84f14f77517580e9deb1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\AuditRequestMiddleware.php' => '607d2e979e3fc327a39aa53c13f5a53af72f331ff7eb3a1854a75c687fcd9d2e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\CustomerOrStaffMiddleware.php' => 'b784c5afa769159bec7a43fb2f16a85598fd3d3bfa2366efba105b0ce70096ae',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\IdempotencyMiddleware.php' => 'bff1a6dd62d1ad28b3a448b9d1d98d9d7ac7e239d6d336210cc0b8db084588e6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\MetricsRequestMiddleware.php' => 'c56777d84280f7a769a76ee7c3977edde64c2253e64f49fe0ea6eede57b20268',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\NormalizeApiJsonResponseEncodingMiddleware.php' => 'f52697c4a00476393dbb008d9d09ee98ad4931e14c4ea4fbed14d1dff5837cb6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\RedisThrottleMiddleware.php' => '82044d71536ccc8e45db63dd840169d88bc602b3c548dd644eb104dbf9cb76a9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\RequestCorrelationIdMiddleware.php' => '41cbe696296d9972f42e246951761af79852efb36a4a2a49edcbd33afecfdde3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\RequireRedisCacheMiddleware.php' => '67ae87c4cf5cf119d3b7dc115d40469ca8eee88c4fa1a8055d28636d15b1657d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\RequireStaffCapability.php' => '2feacef207e07a55342be9a16cc932007a150e11d3c46ca9740a63c4b4db6359',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\ResolveCustomerAuthMiddleware.php' => '6900a288faccc3499d3d66110954bac6da3ec5aa8782377ce6616f7c7b3cec07',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\StaffApiKeyMiddleware.php' => '707252d25f3403020f8ad6dc5f9e3c0f7d7a7836a487df7de2a85eb61d3a2d4d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\StaffRefreshSessionMiddleware.php' => 'b24af4ddb9c6bad5e95f159f7d80f1210c88593d195240d169ba9ff665dc60c0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Http\\Middleware\\TableHoldRateLimitMiddleware.php' => 'cfe99aa4af6b7719cf0ba90e16303322b967cfb2b724e58682dea420d4b7d5cc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Invoices\\StaffInvoiceService.php' => '420252806bc92be73fc9c628cffd9229d064e90dcd909a6a24aa4b35fb24f299',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\BillLockService.php' => '0a4db9429605b6c6193306aae9f6dc8b1f95c1032f2e20d47c50d23a673aca9f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\CheckoutResponseFactory.php' => 'db6bbcd3824c9727dfb331e67ea64fcc929b4e334a8960f9ddabfedf6ee550b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\CustomerReservationBillService.php' => '4e85a27925dc7ccbe8cdd7302cf8ebc00f3f3b998a6c346f8678e8eecb0f4c1f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\CustomerReservationOrderBillService.php' => '3fe017c9b8d2dca467e21ae1f9ee39ea10707eaaea8034ec9582d91b1f4597b3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\OrderSettlementService.php' => 'a744573913587dc2d55ea41f5e0214ea4089b80d47c0a989903967ee9ca6b85a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Previews\\SettlementAmountCalculator.php' => '3934419b759011b3734c67a8affdbc8441edd762126e0fe160713752d4071819',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\UseCases\\Synchronization\\ReservationFinancialSyncService.php' => 'aea89c602cbe4ee0593f8c9ba312851e2bd9cef3b3aa3b971db0a6b7e235d662',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Application\\Workflows\\FinanceTaxProfileWorkflow.php' => '52f8c620f680c30b3208c721efe877e23feb2aa3f510ecd37f126bb58dbedd12',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Domain\\Models\\BillingInvoice.php' => 'c393407f5c34e194c634eb3591edd24254a410497973349af007a7b76d124db5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Domain\\ValueObjects\\PaymentSummary.php' => 'ddb7989c26feb59f847bb71e7746954ac9b643469d1d6a74b9f199c4362a82e8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Controllers\\Admin\\FinanceTaxProfileController.php' => '6898a783ba01a8618e2379ab248a4d558cef45fce01d971a2471c19719359d33',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Controllers\\Customer\\ReservationBillController.php' => '1f17a8beefdb5d0322cd902ead8d79ea647161eac1cce0f97be2f186cf72cbce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Controllers\\Staff\\InvoiceController.php' => '246077f83028f576fb29ca2ba466d0951d6351aafe24f658dcedde159337abb0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Requests\\Admin\\UpsertFinanceTaxProfileRequest.php' => '038a17df35dc26a3004651008242b5f128e0c65d58744a33d1af136c6125d0eb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Requests\\Staff\\ListAccountingExportRequest.php' => 'dd21b5eb6e4b2462a6d2680e43bd9f8d81ad90a4b5ae2b1554f56f8e85ae70b8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Billing\\Http\\Resources\\Customer\\ReservationBillResource.php' => 'fc3d97c3f5bc1d1ff97cf970b7e6eaf098ee9850aab984f1c208efefae2e2010',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\BranchContextService.php' => 'cbb24d54bc21512cf0c4071069d618e3a73d4806e95befce92f671b56fee3a38',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\BranchManagementService.php' => '685dc2adaa01c92fc76853312067f05f401e7262659ceb7d4cbeabf579bf6dba',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\BranchSchedulingPolicyService.php' => '5ce7706285b0c758cf78c35e3466fd010b82106d2ea9b44bff7afda866bf0961',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\ReservationBranchScopeService.php' => 'baba6478d5603cd3d8efb6748446e3067a6f3e75c408aa8fca8cf75828b83521',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\RestaurantTableManagementService.php' => '446183cffd0399388ed27f87fd61a5d18a251f5f2ec64d4da27bfc6b1a1a6c23',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\RestaurantTableStateService.php' => 'e537f1249d1abcb10c5deef7f6059556f51457aeb559b04674861eeb4f37e970',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\RestaurantZoneManagementService.php' => '41b5b110da5a55bbba527af78b5a1d767944dfb15cc1f78520a09aeacd553fe0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\TableAvailabilityService.php' => 'c7f64a0019941dbd135511d21e019d8d4847b457438c3df9789986b25b83da0a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\TableHoldService.php' => 'e218a050be94e7c2ac1ae9fb4d69162cd5a549e250a1171d9d017b23712d5309',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Application\\Services\\TableTimeConflictService.php' => '1e03d45ab3ee2cc4f5b63583dfaf7d4d1376347ff3cb399dfc087170d73a8e56',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Guards\\HoldConflictScope.php' => '9e9636a66581a0a6af163b0f32766db3e16e256e6e86acff2d3d5a17c7f944bc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Models\\Branch.php' => '1ebf4a459076efcfebcf6f5c37daef0f48f4ae5fe23a2f50c63d51b7ba0a886f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Models\\RestaurantTable.php' => '8895af83de8327cff97df82a8ff855c6edd272e9c43d5f0129fb16e9cc9c3f73',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Models\\TableHold.php' => 'bbe793adfe0254efb040d27392ca37ebc977d06d1281da4598286ae2e3e0a9b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Models\\TableHoldDetail.php' => '802e0f7073c6a780825ced0a8ad0b68ec2c99800b967bf6eccb2126be1e930da',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Domain\\Models\\TableTemplate.php' => 'cfb0bbfa2c8264d44dc4278f7371a9db187494c1ef76c12679d8e14a43072b57',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\BranchController.php' => '9103047576cdaef569b0ff07c8f60e9869fccbac0034acaafc0b46dc7e1d8ba5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController.php' => 'ab3b153ebd1e72e1507aee1a73f4461836bbea76b1d3f322085fa6a12498fc71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantZoneController.php' => 'd9dba1b276585ae2f7258392f49008dfc0bd3191f559440e747f8c92059cf1cf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\TableTemplateController.php' => 'a3f9f371b174eef2dee9963e07a0d1ef4420979550aef376a73e63b6583c2b78',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Guest\\RestaurantProfileController.php' => 'a0493b5ead2de691b9ad9a7e828d6a41db3c624d28699ebd1f36466b7fb3c5dc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Guest\\TableAvailabilityController.php' => '22cbf712f3362be2d0d5488bb04af951a4962e0889858d53b737cb91ac8cad65',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Controllers\\Guest\\TableHoldController.php' => '72d098356e9ca32c0e0e69f9573246c7dbdc76a8558736c34a75085a8b62f281',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\CreateBranchRequest.php' => 'e111fec40f2bb5426171c5f54f9bacbbca1cda47611c8b8f7ad3c99e4de7ea08',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\CreateRestaurantTableRequest.php' => '135631dcd6a954082888c848ea28f2e9b716d2167f52f52b90bdafff9cb5e54d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\DeleteRestaurantTableRequest.php' => 'c77e570aaa511fbf607e6f96ea724c2b2b453bfd71c7fa545efd4ae8762aeb6f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\ListBranchesRequest.php' => '0d3b02bccd8388f511133c1a411f0103396b471a2d777c4e397735db98a9ab74',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\ListRestaurantTablesRequest.php' => 'ab41ee6d5c428567e860453cc28ba28865e6535cb4cdd1021694fd377ffbf6ad',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\ListRestaurantZonesRequest.php' => '026efce03b4c98b485fd9d121b8f289245d7aeefa4535125e69bd6c74c001183',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\UpdateBranchRequest.php' => 'f0b6555caf44378a24b71edfee5af18fddaed3ee464580dd1d0e6214e286d1c4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\UpdateRestaurantTableRequest.php' => '666d854cbaceb9d0492cddbdc0c0c4421c45b8558bbdd1b276ce0555b9f12a6f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Admin\\UpdateRestaurantZoneRequest.php' => '378fb945247f00b39f4347d39e7e599cfd2da6f2ea76e943f40d690d9d487a6b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Guest\\CancelTableHoldRequest.php' => '6d05357a4cb6048843a125c085cb2d87cae126385c867a54bbb129d026214455',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Guest\\CheckTableAvailabilityRequest.php' => '25c6ec5b7e4f6af15a395bf8453f1f8f821e3e54ecddc59e5e0f4ca465693ffc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Guest\\CreateTableHoldRequest.php' => 'c70bf951d958fca064e4eb90a561935f8c06c4e9f6aea928ac858efbae301ab7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Requests\\Guest\\RefreshTableHoldRequest.php' => '9b6363b6997eb076e4b6cbd172f98efa23068ce3797a6e34be63a6d17ae8f28b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Admin\\BranchResource.php' => '3a0a2bfd141248631a1656924b8e3d294dc652410df198ad0f7d7d627d74c4e1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Admin\\RestaurantTableResource.php' => '3e2416a75ba6ed1c6ee127842eee432cd544990770b58cb6d3dbaa5a71eba3b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Admin\\RestaurantZoneResource.php' => 'ccbb3fd1878fcd26fc9ec12da936ce67a388d543fd99bab2ed832b35815bbd85',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Admin\\TableTemplateResource.php' => '14f2b15e3656a3a98781be9ccfe5ccc8d999bc5464d10f4263884a4684fcd92b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Guest\\RestaurantProfileResource.php' => 'fe75cd388bf391d8336c8e30773f0daf7f4cc84e23480ed48aad2541bd3c58df',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Guest\\RestaurantTableResource.php' => '8b0626f6be81842bf37dc5daa591629d9d30dd9d45a28ec5999281e697408ebd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\BranchScheduling\\Http\\Resources\\Guest\\TableHoldResource.php' => '6293d33920687611d227af7ed111bf8b58c2b1ebeb5711bd03c5a03066e31222',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Application\\UseCases\\Realtime\\SettlementRealtimeEventService.php' => 'f361992f5a669bbf3724aa67e04d9d6ce1c1400ea86cd6df8ab444cce15c46a2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Application\\UseCases\\Reconciliation\\SettlementFinalizerService.php' => '327aa70c455dc36c575834382cb950857d895a05ba07734b024044e0d5afcb33',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Application\\UseCases\\Reconciliation\\StaffFinancialReconciliationService.php' => '7e8d580528ad6fd821650c57529dc6751074fb7d0476dbcb28bf33fd212bbcc5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Application\\UseCases\\Shifts\\StaffCashierShiftService.php' => '2ba5a0eccc3b7fb7dbc16b41e422f671262912447cad31c7eed6396f3d7fb7c4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Application\\Workflows\\OrderSettlementWorkflow.php' => 'e6a14295335fba7c39600532234dd2b7f0275df9742e9c3ae571ac9e1f0aa04c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Domain\\Models\\CashierShift.php' => 'a3e527b1cb36e695913c0b7e85b52fd827debb18e0b25e435e709a1fd820e618',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CashierShiftController.php' => '285277719c4e5fd3d3c48ca3294fa6472717c2bb5c1b4e988a3dc7ba12d32c9c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CheckoutController.php' => '6dd7e1335fcbd8123f44fb731bcf37f101372203f4df768941dc1c031cf0d435',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Controllers\\Staff\\SettlementReconciliationController.php' => '3d9b906d6cbdebcef3c84ca192c082dfd79d95f7d1d1a23561acf2f3e06d8713',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\CheckoutOrderRequest.php' => 'ceacbc998a0429e9178d5b2dc580a17abdf2cdf678f2b835e30e9e058cac6dd8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\CloseCashierShiftRequest.php' => '2ccab571da01189af3e67ec21a5ba91f62d8f8d84977ccab79a42088e2b8a1c4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\CloseOrderRequest.php' => 'cd0628efb4154cdb11342835b9a3ef40a148acf26ac674036ceaac545dada8fa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\FinalizeSettlementRequest.php' => 'afe3650a87a8509eb4b0c01c986b212b19c0d867fbf78c5dc9d3a6b284078805',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\ListFinancialReconciliationRequest.php' => 'ea6a41589494abe922dee730db9b419759d0ba7736e51e5ad63527e646eeff71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\ListStaffCashierShiftsRequest.php' => '4b28ac9492864bdc6b22627d929f146495f43c8bd9fe83c4d3420fec2417be22',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\OpenCashierShiftRequest.php' => 'c18f883e1678bb12a15bdffb7421b659714911fff69e2328ca59bc1eef131b17',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Http\\Requests\\Staff\\PayOrderRequest.php' => '7002529c08b50ec2daff3362a56570bc1600af459d0c280a3f89f719d32a6b80',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Cashiering\\Infrastructure\\Persistence\\CashieringReplayRecorder.php' => '7cdab3eb4fb3cb21845c4f801eec2d6421a21dbce2f3cfd722b3b409e096000f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Application\\UseCases\\Browsing\\MenuCatalogBrowser.php' => '84cebbdb64eb6d14a9c5e83fa898077fbab6103129d63b2c36cfb38bd504b0d0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Application\\UseCases\\Management\\LegacyMenuService.php' => 'e9131984fd222977d0b62c01791a09e43bf74e36a3824d902dfcb9d0e3be68c6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Application\\UseCases\\Management\\MenuCatalogManagementService.php' => 'ab797749a520cc0407f2a9978e89721f38df935d7538dc6105962ec5b12cd0df',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Application\\UseCases\\PolicyPreview\\MenuPreorderPolicyService.php' => '9b2ff470d82e533dd1721b8b070803ccc6cf121271daa06729b515297032220b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Domain\\Models\\MenuCategory.php' => '8fdb80324c015b211bb45b9e38d1be6df8b870d2cce6f44315ec7e56e105ef50',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Domain\\Models\\MenuItem.php' => 'd27c856b07c4ef85d076e2f13d19bc6826c45d6e41d60d0b3e24be35af7c6320',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Domain\\Models\\MenuItemPrice.php' => '7f7935aa3d1b3becee2f0e5756f8f70dc87321af27647afea6909ff22211b9ed',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Domain\\Models\\MenuItemRecipe.php' => 'a26059fa6a1b8c385a0453f914f526cba6ae8871f35a085fb9a9252299a5909a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Admin\\LegacyMenuController.php' => '42e1421f467d71c969530e89da5da7dd8a2f4f940cb234f9d70fe1bb1f789072',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuCategoryController.php' => '4931a0e9570053b0bb0cfea51a298bf3c7cfecce9bc552ebd2479736a8d3354d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemController.php' => '11f8d587b501fb66f10dc81ecab49c9c75ad3c1beff2375d8793496697e085e8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemPriceController.php' => 'e0031104234f30571e4f1a31a15b04e68c5165859de9a9f8ecf1d238a120c437',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Customer\\MenuCatalogController.php' => '20a0d5cf93f7db6aa99a10b994f275fcac5ba607e0113a8637f80ed5d1a4b6ae',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Controllers\\Staff\\MenuCatalogController.php' => 'fb5788fcdb870d705c586df13d0c6153d4a578fea7b17e300161425eadc71966',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\CreateMenuCategoryRequest.php' => 'ced4959a4146ce7b416187881db00a79be28521f80dd60aae46803d7cb0489af',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\CreateMenuItemPriceRequest.php' => '7f4c5ec2412d1d165c50ff96d6fc895284e99071de59f704b0d353a9dfb912a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\CreateMenuItemRequest.php' => '7a6377b6522ffa44802922123181661604980163252bafaa0002d79f855e07a9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\LegacyUpdateMenuCategoryRequest.php' => '0dc8386b87d63cce0d78e7a70a66506db2e1ffd5412f96d7cedd76206448dd22',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\LegacyUpdateMenuItemRequest.php' => '6d7882b6d7a0e493f2a66eb23f778e5f71562950d5271a06ee43a8a253e5032b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\ListMenuCategoriesRequest.php' => '3a8bb5630c1a4ece4872bee2ea3f882544d5189d8e89fae547266df45dfc11f9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\ListMenuItemPricesRequest.php' => '17670b1e988b942d25b554ca1eadafd49e4759c76bba1a8242ae1928833359e5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\ListMenuItemsRequest.php' => '420b138ed8b825eb1717c10bf06df968bed1f6b328e952541c49878d133787db',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\StoreMenuCategoryRequest.php' => 'ccc39b88464cec396ee6ab5909e06c4b4d75449afc1c47e8efa5dee5983b76cc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\StoreMenuItemPriceRequest.php' => '3c51f692d332cfa1e668381e73b3974d4b97032dda2f868e7608cab65e80127f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\StoreMenuItemRequest.php' => 'bccb0f33e7b5b63905a0126e26207f2b1bf08078c35aba2995a6a07f859ead80',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\UpdateMenuCategoryRequest.php' => '351d421bd6458cf2837cf98a549db214c0cc8b1bffc8aa863c6968cdc04d1a21',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\UpdateMenuItemPriceRequest.php' => '2bae52baa0da2cc46043f5453465754d3a8d5ffc87fac5799022ad3880d341e9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\Admin\\UpdateMenuItemRequest.php' => '3198940dcbcfae21a460efc0c51f3a6e11c177656d1c1aec064e01b34747161a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\ListMenuCategoriesRequest.php' => 'b7fb393bde58a2ef4cc5f9f90c8642d00caf0a0426bbcc664c1c030a5aec43e5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\ListMenuItemsRequest.php' => '874bfb812f06511bc17a10d6479f9fceb85e5fd328e270b613c7917c9a2edf95',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\PreviewMenuPreorderRequest.php' => '5327ee32c753ef2d8d34be4a8e0679b580e93f657ff44f13a339bd4222eec621',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Requests\\ShowMenuItemRequest.php' => '6158d2faf5a361f840705e672333fcbf007f2fd832c443e520791dc31c3ca4d8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\LegacyMenuCategoryResource.php' => 'ef0620e4760a5b915848a15cb24f7afc678100d834665c44e1d50180c139cf6f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\LegacyMenuItemPriceResource.php' => '42872e3ccde0fa363d283572b7c8118bb36d9f2b628d4ca9e03571aa66b9b83c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\LegacyMenuItemResource.php' => '9a5296a796537208bdb529724dd942bc93a1cf6f48645f6a714fc1f3c560fae7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\MenuCategoryResource.php' => 'b9ae72f5f96ffdd2660c8dbda392c61cf8f97f9753de85512927bcae1ad34d0a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\MenuItemPriceResource.php' => '2e844ecc679c982ac4c22c9de70314907ea2f8b4a9690a94d6c93e6aa8f81a06',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Admin\\MenuItemResource.php' => 'b9d59fbd005a765ee2478c0c9407afe5bfd76eec513e4c9bbbf3d8db7cf1a831',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Customer\\MenuCategoryResource.php' => '24cfdeed8445ddcb2d5927b6fb65f96213e6bbb99299a1cc95fbed6d4ad2fa40',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Catalog\\Http\\Resources\\Customer\\MenuItemResource.php' => 'f5d23878d2b494cac9d83d508653618009ebc336e76ef169a41e6b9960c2d5e0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Application\\Services\\StaffConversationFileAccessService.php' => '8826d048dfa7f3fd8b6f5a9aec04cd210fc9fa0e2f07f9bffaf44404f104689a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Application\\Services\\StaffConversationInboxService.php' => '3cc92904e35918ceb3c9428ec7436bf7d57e5a8983bd0039b248f5247419cd0e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Application\\Services\\StaffConversationOutboundReplySupportService.php' => '8e4af43af622098c8e087bae44e6df6fef922b1362e83c8e6a10c472f99f3ffc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Application\\Services\\StaffConversationWorkflowService.php' => '4679d7f8e7865e8da0ba31e4aecd79dc31daa33f6159301f632dcf8224dcdcbe',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Application\\Services\\StaffReservationInboxService.php' => 'dafbafd1d843b63c56195160bee28375c8260417c76541dd7f48e6a59c236acb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\AgentAssignment.php' => '5f7d0383fb473a004aa433d74d42ae49e3c2f4091ec5fcae9176eaddcec4207a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\Conversation.php' => 'c5ff28901c0e14260c30a849240e04c640d50723cd35040d9359b32159cc5c30',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\ConversationAggregate.php' => '40b12d61b6a40535a3e4c1275c8f7b3a616a54bd7d12023ac43d0bb8800c2764',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\ConversationAnalysis.php' => 'a3e11b0269d249280bffc461b874f76c2c559dff1ca172bda15db8f3d5cf10b6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\ConversationEvent.php' => 'd3bcb64553eb7ffa1ad979750c4ed732c849ee91f0f178ec5ae9cfe9717ee15e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\ConversationFile.php' => '1dab6ea9e6f21fac007531e1d0013c6e239f19b17e4242e53031cb7ef6faa1f5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\ConversationMessage.php' => 'a87951558e4ffed2f3bd8fc1b54658719e4baf1b137996a92c2e26395fc341dd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Domain\\Models\\MessageEntity.php' => 'b666d6a88580789d1d2faa8197e40eafd78bd29d2ef53604686c75a1d6ef7726',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Controllers\\Staff\\ConversationInboxController.php' => '4043c3aba4dee2c45651670442cf07a72679af2a670c8940b2a215695ec0962a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Controllers\\Staff\\ReservationInboxController.php' => '57aa461ccfd57da5fe007cac5e04bd28624dca8955c6207b3ff444feb0a943c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\AddConversationInternalNoteRequest.php' => '5c229035e6b92f1ec7953a7101531d723eeac6a75cd8dcb9adf3e57c96119281',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\AssignConversationRequest.php' => 'b971a3ee086a507a0e78db26c5241d33bc881109f92a49b092e842287d449566',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\LinkConversationRequest.php' => 'ee5e6438ff17a7c2f78d73dbe1ceae6384a279ded77964219f4df161b5e0010a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\ListStaffConversationsRequest.php' => '5e1da64240cfb99a94f04d9b60e056bb99f1aaab81023cf5a3bc930beea67d4a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\ListStaffReservationsRequest.php' => 'edf72a4a3b1bc9d9c7dab2c68ae97088093ce7dc5cf1b791dfa3181190609697',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\SendConversationOutboundReplyRequest.php' => 'a05bd6a0625942b036e835fa4a7e61295700b41b264551b555169680d79bd7ce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\ShowStaffConversationRequest.php' => 'e5ce01e13b70b67f4520ce18e9555faffe692e39393b9f73adfd6a259af16e9a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\TakeOverConversationRequest.php' => '83d99bb1042f9d2f347e57c33b4fec4b41407b6f755e4a9c1f648e843d4a028e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\UnassignConversationRequest.php' => '5e0c167d5aa2f912e4608956cefab836d28e0b57f809d999da44817e27ef9dd3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Requests\\Staff\\UpdateConversationWorkflowStateRequest.php' => '08fba0d1ae56600fb73bc1cadf278035a9cc07e105a517e29140c99a7fabab84',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationAiAssistResource.php' => '6b2803305bae231858fbf77dcd9a37eece2940ec794cb67816813566293ff595',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationAnalysisResource.php' => 'de9c4fd8b0a2d33d40679fdba8425a14cd0f8efdc9cc88f88d956a3319722ed5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationAssignmentResource.php' => 'd90795e6c73c76d928526c6a2e78dbb881536e8480c382f2a558a60550326933',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationDetailResource.php' => '77a48d7754e8720ada06323e95a184f2f2e6d064ddb163dfc282049c72cca0fc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationEventResource.php' => 'bc4cbb9b850205788d001019c9da913740a7121487ea5be8223381350c9e0dea',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationMessageResource.php' => '046c4ea6922cd17196b27f196072b61990085a702f175df7567ee9d44b2d2f18',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffConversationSummaryResource.php' => '6c0a0e45ff8ed5dc6178d69fdfd7f028c34b7782a241994221f8df6a013680b2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffReservationDetailResource.php' => '427efc240f096c6c7755dfd456bc66d9d35ee0bbb4219354e2e74f9ba2a0b8c4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Http\\Resources\\StaffReservationInboxResource.php' => 'c58fcbc9ddce2b94b0d4bf8ee046cbc68b0ab9c2386a2632efdd83c551b84d93',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Conversations\\Infrastructure\\Internal\\ConversationAiAssistBuilder.php' => '7276673623ab9e7d14feb1e0234b04c72dbb6b00a7c0195456da5a2a65a9cc5b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\Queries\\StaffBranchContextService.php' => 'e9749801ba697086330fad833e2dfc99cffebfb5684c6f4541ad24fba511d2b1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\Queries\\StaffCheckInReadinessService.php' => 'c1183697d2bdd5364ce280fabb51fef2deb0704d0b7c4a1d339a252e04edc105',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\Queries\\StaffTableBoardService.php' => 'e9f01ca3567a1a5944fbc6d7ab2be68ecbf581c7764f713902ae4cc5fcb95e3c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\Queries\\Timeline\\StaffReservationTimelineService.php' => '7502451d151b956430c9bc5c0cdfccd03a6477357c74e59ce25546400dd0aa79',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\UseCases\\Boards\\StaffMoveTableService.php' => '13d457449bf7506775a78f64dd833197f9d8cdd49262652953fbe307fbdf6f4f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\UseCases\\Boards\\StaffReservationBoardAssignmentService.php' => '60af5c478a3ea842e86c18f12b2f164a639bc954bbcd6d8ffbf9b8e9613b271f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\UseCases\\Boards\\StaffTableReleaseService.php' => '3b18dcda97c5f02b0cf4e2d8d40df84358785fd465393544440e08f6c6f4620d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\UseCases\\CheckIn\\StaffCheckInService.php' => '15e75b670da9f834c46ab26703ba73038a17f278c523e72fdaec00602ef416a8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\UseCases\\ServiceSessions\\StaffServiceSessionService.php' => 'd37e86715becacd737b2800ca1548008c427fb82ede8a9ff7fdb43c77e17b066',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Application\\Workflows\\StaffReservationTimelineWorkbenchService.php' => '3bd61d29dc8ff1e43c03bb9f9185abeffc68beaa0680146752ab9fcb2385ef15',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Domain\\Audit\\TableStateAuditLogger.php' => '5996207d22cb6b1f0ec7b4c8fbb2ebf80ea7adb9e12ef5b667a457c3707c94f0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Domain\\Guards\\StaffReservationOperationGuard.php' => '7d98eace1ef7890f6a8362c7667f18776446a105ef707664d76b517015154eca',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Domain\\Guards\\TableReleaseGuard.php' => 'ecff3c445f201bad18fe2a4de7f8394a9fa3ffc87ac0d85c13016658cb27c1c5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\BranchContextController.php' => 'eddef330df5d74f01932496d54476fc8aaf0767db17e2590dc4d9e1eea2183a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\OperationalChangeFeedController.php' => 'b7c70f33f661673056e57b0f8880a011690a1eb7320bb10cde912c11f146855b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ReservationBoardAssignmentController.php' => '7dbbfd91f7b1cf9e1c925406ea4216fd8162af4c0de865e6ccf7caa8c28e9856',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ReservationCheckInController.php' => '6d22a998dab8d2e420a3c8acf7dc7d6e9974e092aa7797dce57bbe69a7b130fc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ReservationMoveTableController.php' => '0465fd80ccf0b2004c625c2169099899d87d01721564cffd239bacb4b908565c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ReservationTimelineController.php' => '427de53a26677da21d3c7fb3d844559c840c5d996592be543ebb9904c5d242d4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ReservationWorkbenchController.php' => 'cd0ba5396f20ee0af9c81b8c2091816cad492a6d59ac8785959ceb523c39de10',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\ServiceSessionController.php' => 'a2b60681b931027e37eaa3d8dc573de2fbe0059c750ead1dad6c4ab936a368b8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\TableBoardController.php' => 'e98a1161ba27140e5a6debcdfb079da1149a282fc84e32d635d9227e2593a4d6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Controllers\\Staff\\TableReleaseController.php' => '6f25371b79d1450746ecd93047fe6263bd8c4f4c013c47cb2ed10a6504835c4d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\AssignBestFitTableRequest.php' => '4136bb0ad8274585b5b23d2f5545e72b8af4be62c246009729e924bb29557b33',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\AssignSuggestedTableRequest.php' => '8517e077f8776bc234e4ab983d06322c20d7bd804d5441b6d9f6b9defa36a8c5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\BranchScopeRequest.php' => '97a2cc08240a3cfb0e88cedec03c9265d888044d9066170bd8b7b423619db934',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\CheckInReservationRequest.php' => '31b5892950d2036640f9925c146596688f240596b633730113298769194e4514',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\CreateWalkInServiceSessionRequest.php' => '1928b94202ff40e630e8c5a87d2aff5ce08ba9dc64ccea80feac20da72965f5b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\MoveTableRequest.php' => '24ffc6291efe1d8c01fa99d5bfc3de6c3840e6e556fed61055aac11263a60d82',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\ReleaseTableRequest.php' => 'c010179d7755cfaead2573073ae1099b6677aef2af91e90010ce3a2a4289d68a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\ReservationTimelineRequest.php' => '99b1d0f36e1d97f3fc58aa0cba806316765a42687da1fac6b42d724975bf6d96',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Requests\\Staff\\TableBoardRequest.php' => '83150403b5fbd935350c3a5603ab0da4250d09c9bcec39ea56f44de317cef6c0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Resources\\Staff\\BranchResource.php' => 'e35cc034903442f13467f7f4f7028d677d730936b4bd53f63b7a75af7d4a6698',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\FloorOperations\\Http\\Resources\\Staff\\ReservationTimelineItemResource.php' => 'a186e2ae3f454f87e487fc1bde08e1089501df340f4f02d71d8761abcdf3bcfb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\Queries\\StaffCapabilityResolver.php' => '287590b8742aa7b641287e5af9027a3f23dd7d5c1da111ae8b6aa65ec6586fc8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\ApiKeys\\IssueStaffApiKeyHandler.php' => 'efdbd7584b9535989e12a6ecab44e364fec5392e44dc92320b8b5200507f9bdb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\ApiKeys\\RevokeStaffApiKeyHandler.php' => '828c5e0045259e4fc6ac9e2a52ea5ae913a53ac5626db86d4fa496fe881003ce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\ApiKeys\\RotateStaffApiKeyHandler.php' => 'b940251a5b5348a898b4579add98c74fa6bbb48c65c1640c56ea914d014ebd36',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\LoginCustomerHandler.php' => 'fbbc04e3071a1be73ae0f9c33a30f88c2a0fb4d471694895bdf3d916297027d8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\LoginStaffHandler.php' => 'd5fe154b0a954abc912e6aa1e9cba17a4b4e118b810ad82482f6fa2876c725d3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\LogoutCustomerHandler.php' => '33aff0ff48951d3fc5c1a9b33d79e6d504b60c2af2a711b22c9ed5af4c97a8fb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\LogoutStaffHandler.php' => 'cd11c1bc2f3babe43333ad70283f3dae89161bddd88543000c9e8c4e4792fd14',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\ProductAuthConfigurationException.php' => '35537876360d9ae55c1424b382aefdc25e8d0ba38463e551d1b0bbabd7bfebae',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\RefreshCustomerSessionHandler.php' => 'bd578103c8561ba385306679144e0a2940c5091c4a8bd68e6b9e39e1a38e2256',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\RefreshStaffSessionHandler.php' => 'e608eb563e868aaecf17d560f159b832be7efcc8053c9cdc45a1cef30a6a5e7d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\RegisterCustomerHandler.php' => 'b26fdbfcdb9323d8a367fda7654dbf4d942a43d54be675b365de28a4f8a533e7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\ShowCurrentCustomerSessionHandler.php' => 'bfe8403b1d7ee99248d7d5c333a57d3dfed37796098fa7d4ab78e9bc69082b81',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\UseCases\\Authentication\\ShowCurrentStaffSessionHandler.php' => '750bf4659e25072a97c526998e0bdf9e59d0bb5dbb8b80f4457ee57f8bd55735',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Application\\Workflows\\ReservationSessionAccessWorkflow.php' => 'a51815cd1c811ca2250d64b36af83b6c9eb8a36256f558c84f9d642353f804a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Domain\\Models\\BankAccount.php' => '333a72ccf51a7944db89e177e8c2421e18359bcda70916e24c9455680a1cdfe1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Domain\\Models\\CustomerAccessSession.php' => '4200eb631bbc49306b9fced0c229153b244c21657c9fd0d6bf823c59ef21a27b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Domain\\Models\\Role.php' => '1eeddcf57300d935a12eaf3f747ee68b5745efd758fe72001171a9df8e516099',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Domain\\Models\\StaffApiKey.php' => 'edf0867e97021b4a11cad9794aca8966056e6922d49a553323eb3e3b992d672f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Domain\\Models\\User.php' => 'cfe60461d16498379c858a855feb16e57b7e570a45f545ce9e41e0868f640194',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Controllers\\Customer\\AuthController.php' => 'a3ebfe68edd5e17dc54ee0c63e0727904387bcc087639584c05f0bf4504cf7fd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Controllers\\Staff\\AuthController.php' => '12ec7e360cdc50aef1ebb51c16a5ef0fef68ed482daf36ab9ee764d34632a7af',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Requests\\Customer\\LoginRequest.php' => '24cbdbd661e1327720ddc8afa940f9a67a2e90d7de5bfd96c56f98131938a84c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Requests\\Customer\\RefreshSessionRequest.php' => '418d48b0f6bb13572dabc4e41a1520cf3464ff9c7b96a3d73dac5f0b432b454d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Requests\\Customer\\RegisterRequest.php' => '2d2c5915bee69ff0cf3449a9a8113dbdd3f48de7d0def62950d6c91e82e026d5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Requests\\Staff\\LoginRequest.php' => 'a7f41f9651fa667ef41ceb7a242f706bee891638d3663b41ba1bcd6ac153c976',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Resources\\Customer\\AccessSessionResource.php' => 'd56983e22ca8e1dff4df6c1cbea9a8e9cdd985173d7ffe0ba20d7321aee426de',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Http\\Resources\\Staff\\AuthenticatedActorResource.php' => '88d5fa747667e9ee7713bdff2e50a17029028cf2e5ab12fa003856d8015c1c92',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\AuthenticatedStaffPayloadBuilder.php' => '54020ed967d59104bd51fe26ce97b7cd6dfe03e0b5384c1c5e854643a9b1a9b6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\CustomerAccessSessionPayloadBuilder.php' => 'ee78326a4534ad1e9f874aaff73209a421fc7f8a6f9502910d6b62acc1fe6709',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\CustomerSessionRouteContract.php' => '29ba542d0b1f775709d250bc873014b447344ff20a9adbce8367d9784a2be862',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\PasswordLoginAuthenticator.php' => 'c1b7d1931d1cb069699c86ef5c116191cc97ff1532ae05330383a491dc340613',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\StaffBrowserSessionCookieFactory.php' => '4f98c9a80decad4eebfd5e569e80edbdb1fa7ce1ca911da3711779f9478b41f4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Internal\\StaffStartupContextBuilder.php' => '4b197a8595d0c75755b72ec58502677d0e7a658be70d2d33470f8f9d52bf2ad9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Persistence\\CustomerAccessSessionStore.php' => 'b34146d008607b83cdecc38da35655244526c11969c7882a937b72147c77b32c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Persistence\\StaffApiKeyStore.php' => '1adb30de903d3faa71c68eb303c81a0318a058f4a65fe0c8f82e68e58f84632e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Tokenization\\CustomerAuthTokenResolver.php' => 'bd283307dfc4fba64f94196deb58ba3d179305ac71a796780c4a6c293ecae02c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\IdentityAccess\\Infrastructure\\Tokenization\\StaffApiKeyActorResolver.php' => 'c61d3fe879bd89650ef1fe8b052e8d27b3828da4d20af8a7a9904cce8a41ee78',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\UseCases\\Inventory\\InventoryManagementService.php' => '6934fedadbe62888a7d1c4f3674abd61b3c14fe2026d7052f71fb7827e4d0b0c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\UseCases\\Inventory\\InventoryStockMovementService.php' => '4ac072fa25aef0436357746d8228fff70d1b43a05014d12b5e236035a71a60dc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\UseCases\\Inventory\\OrderItemInventoryConsumptionService.php' => 'c01e32303d92ea50071eb21ff3301dd7a4ca92cbc8c8d9762a63f5a0d8be714c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\UseCases\\Procurement\\ProcurementManagementService.php' => 'e5d194fcaa82394d7ad3986e68bd969692470ab9e748b9cb124a98c15bd01caa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\Workflows\\InventoryStockReconciliationService.php' => 'a422e6b00abb3a06ec66add3a2d54a67ccd4449b02c19b65683242178ac734b2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Application\\Workflows\\PurchaseOrderReconciliationService.php' => '774df7fda32a28a802455c706c1d7dee7b8d73d0b5821bde22bab23452a3485d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\Ingredient.php' => 'd7d3ddad7689a06d44317f5d2c2ad527bf3a42e999c282ac2ff1cb67f9d6d571',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\IngredientStockMovement.php' => '3a2ce1ee688991ee68e6e912287eba2751a24e23d68041d957c81dd80fce49a7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\PurchaseOrder.php' => '4ad64231ca34086bd3e13e32329f5dccd2b957bc09efa28a4b3e07b8997d0187',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\PurchaseOrderLine.php' => '3761515f047d6d860bc3cbbd89509b92a17bdea4746f5590b0371ce8e47e8177',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\PurchaseReceipt.php' => '64e369e7844ccbd705731dea40d5abd95068e9d065b959ed935ac4d8daecb5f2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\PurchaseReceiptLine.php' => 'f8b92fad42ae08a9a3de2cc483c6645df0a64e1755e00fd8c3ced38a48bafb98',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Domain\\Models\\Supplier.php' => '6ffeb3fb418254851b12f28fd2090d73412d7253352b322ffa3de5ea3c4c96b9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Controllers\\Admin\\InventoryAdjustmentController.php' => '6aff9717a79f194d264611cc5dec2c566c732ed40b66815041f76235c5939c40',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Controllers\\Admin\\ProcurementController.php' => 'f781e71d9627d1ce069ba268a8d99b7d180d74684e429c6b683865d8a1cb1af1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\CreateIngredientRequest.php' => '9891077c986311e3e7fb3b991b92c0360f9bfd14ca4127e44292f3ed47c44fd3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\CreateIngredientStockMovementRequest.php' => 'df326fa04ea24c02d918c623ba5b4e74ee144ea1b92b5346ef69bf2f1322a2e0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\CreatePurchaseOrderReceiptRequest.php' => 'a82393b6c996c73f9d8b34b810520226f8fa49e0e2996d40906b915d8c40c02b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\CreatePurchaseOrderRequest.php' => '6698d4398c24682feebeee3bc5762d864bb46562f5a88a7384607ceedae397e2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\CreateSupplierRequest.php' => '41c00417a5e830a3456f6746660bea71569d55d5d9c9d77152de995bbe5296c7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\ListIngredientStockMovementsRequest.php' => 'c41cc31fdd3c7ad605ec841176daf4756a0285a9a7d4dd86598ab8b2202cb64f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\ListIngredientsRequest.php' => '5554869c88f43ea2f2c354e2d62bf852e8cebc216ff3544a24642239bedab566',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\ListPurchaseOrdersRequest.php' => '2771a74e21dda6319b34237acdb2bd3385a1b2e9a1cf8ed3c5824213e4cdd644',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\ListSuppliersRequest.php' => 'a08a9850bd0e6a0407caba24ad81ad05673052518740ac6db60c417bb8132792',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\SyncMenuItemRecipeRequest.php' => '54585907eefed76527f00cde787d56042bc88bd1c8fccaabf7c56ba68590a048',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\UpdateIngredientRequest.php' => '6d54a5edb8869f9e4a4420f0b2121a348e9729f0717a371f1fe92a1c217c3379',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\UpdatePurchaseOrderRequest.php' => '43a7b606a06f83b373726108c24e580c0b9e6c9e0c85f9b0b10c14e00dbe42d1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Requests\\Admin\\UpdateSupplierRequest.php' => '02d316c749b47b464e1194336877e71a77a074837d49fee09ce7897fd2f9be17',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\IngredientResource.php' => '13f57728e0d7c8c88d92603d5315fc363a5367639a7e99cd3d455f0d657009d7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\IngredientStockMovementResource.php' => '47d223ca70fdc59f4310f2d499a449b192eeadbbecc6948ac826e9de3a7b3d21',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\MenuItemRecipeLineResource.php' => 'b5271d4735ce7ba6aa1dea00bae34b68a017607914c4ed8128e735cdd79bf25a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\PurchaseOrderLineResource.php' => 'd384bbcf0ebed8a7f2c14dfdf4015b0080cad1918c304c6d411d18deb2153ce9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\PurchaseOrderResource.php' => '739713b557715e0b58fbd52a84dbead2d0c51a926e2bdb436ce15fff4deae874',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\PurchaseReceiptLineResource.php' => 'a80ee68560359361417e3213df1e0ff65682965a073d83a38280e57e16190832',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\PurchaseReceiptResource.php' => '224eef291e211cefb6748eba368271897004a789da90aa6addde0352ac13b6d1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\InventoryProcurement\\Http\\Resources\\Admin\\SupplierResource.php' => '868f0e12698111c9f57d19eba2dd7018d9367e5cf87f6430b734a7bc927319c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Application\\Actions\\DispatchKitchenOrderAction.php' => 'e3cb5a8cd7b23404dc3b69b186af016437476f87d9da65ebc174d385f6a2cc94',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Application\\Workflows\\KitchenRoutingService.php' => '2209cc15e5982330c80a54b7fe108352959964c7b302c502f629e97f9a41b529',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Application\\Workflows\\KitchenTicketConsistencyInspector.php' => '23662e172909c51c3e277d9a280b30cd7dfb7d9f292940fe8dcc3f7facc8df92',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Application\\Workflows\\KitchenTicketReconciliationService.php' => '9abe36b11b552d4036ebe898fd94e6bd543f275a19f350f16826546f5c59894b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Domain\\Models\\KitchenOrderItemTicket.php' => '3254aba1a279256c1774d1da69bb86a94449f04c55d61996eeeca301c1efe8e1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Domain\\Models\\KitchenStation.php' => '99ee3aeb0b4caab7c790a9bdef725c184da8b73f2f7641015ff4fa2f320b41dc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Domain\\Models\\KitchenStationCategoryRoute.php' => 'ba4d5d62c10a591a9d68afad422e7538b40b63f210c00bb26ea516925faddd65',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Domain\\Policies\\KitchenTicketTransitionPolicy.php' => '9501911b24bc0436932dc8aca746bc8d3bf8e295b99e5064e76f0f5222a12226',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Controllers\\Admin\\KitchenCategoryRouteController.php' => '3b7da2fdee695237393c52f3c9f0955648870bd52d0b34a442f95d9344b1efc0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Controllers\\Admin\\KitchenStationController.php' => 'a31ffb2bf4a132be7aedbc1b0d807b495447a372550015e03dabb177557a8df3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController.php' => '9afacfe369f446058b9953de0d98fafb7dea5066ee3a0a5fe78894f19a2d61b1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Admin\\AssignKitchenCategoryRouteRequest.php' => '34aa995f193363f8992e80d4028ad85b479f8bf1e6905deaae90d7f5ae2af268',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Admin\\CreateKitchenStationRequest.php' => '72f9d5a88dc3e27e0c7ec658a73e7dce4f25da1a83827148cf717a85a4b6c14c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Admin\\UpdateKitchenStationRequest.php' => 'c1c6c677e0b00cff78c036fbbada90dd43c08da25b641dfc488a4b0912639d7c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Staff\\DispatchKitchenTicketRequest.php' => '5743b02cd24bd7e381ea5631a02d6b7fd21a7560b60ad40f99321785fd6a66ef',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Staff\\KitchenTicketActionRequest.php' => '78713f603ffe114a7e0385742ae066ee443d4d0876f3e47c62790a8410bf4aed',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Requests\\Staff\\ListKitchenStationTicketsRequest.php' => 'e7c20908b8907f4f544764138a1c19cd9cb362c48a453204f93a7306a75a443d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Resources\\KitchenStationCategoryRouteResource.php' => '3bb5d1940bffeda79583cddcd7f4fa9ee5efeb45dab48baaaa30ad7ee4c4d30a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Resources\\KitchenStationResource.php' => '716b3759d4e69ef42e330c410cc441b8b8f80959216be474988285b05bee1e21',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\KitchenDispatch\\Http\\Resources\\Staff\\KitchenTicketResource.php' => '851aa5f5cae7e5b477720c36af919fb9eabde78223550fa3bb8b747236599251',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Points\\LoyaltyAdjustmentService.php' => '1debe1d024211d8bbb90a3b2198010637d60de26a94c87e5017a8ccc3038b768',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Points\\LoyaltyBalanceService.php' => '6dfabd5280f0a95aeb65f5d950fc06df6cb846f03fb8a37632f8b0b5979bb9e4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Points\\LoyaltyPointsService.php' => '1b3004957f63c37766e6055b40051e237cc6b5865c413487343f7a280d93f0e7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Points\\LoyaltyRedemptionService.php' => '78066db559c7ff2e30d718d6e95d43b3e6949968dcea5d15df53c3945fbbb5e8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Points\\ReservationLoyaltySummaryReader.php' => '420f3b16242954c1797f773af2b77081b8c592077a8e72173e2bf095b411c82d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Settings\\LoyaltyRuntimeSettingService.php' => 'd37cdb457d11168da9d800221feae9df8bcb907a28996950030ade12e059da59',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Tiers\\LoyaltyTierConfigurationService.php' => '9073ac413433a0908c2dcbb85689468671b0cd035872292f820e59ac3fb21821',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Tiers\\LoyaltyTierManagementService.php' => '1bca20baf16fc9036e009ec9965bcee70687f793db21ad837628111ad430f62d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\UseCases\\Tiers\\LoyaltyTierSyncService.php' => 'af92bb8bd4eaf85c696ec47f5c611bf3146553184bff85d9a489b8957ac10b50',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\Workflows\\CustomerReservationLoyaltyWorkflow.php' => '8d1aaf762505d1220bd5c8156591b9945e4636bad777b53f9e6d3a5f76e8a7d0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\Workflows\\LoyaltyCompletionSyncService.php' => 'e5a56d130602d84fdb61f66459e107443abb17f2c2880ce70fc3068de97e965b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\Workflows\\LoyaltyLedgerWriter.php' => '1ea5ffcacee3700c09d3eb8268fbe3eaa5159db2e12cf40cacf746e66e7d6279',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Application\\Workflows\\LoyaltyRefundSyncService.php' => '38fdd40c28bedc34da215c8921c5b91b0f792b08e8434783c15a239a6a8f2e31',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Domain\\Models\\LoyaltyPointTransaction.php' => 'b21c5d510da12a535e1144b7618ef5af41349e6fd9eac203af84119b180397fe',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Domain\\Models\\LoyaltyTier.php' => '2e697ef680093b98228daa37ea525c441779614d0aeb1979e91ab4caf5be172a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Domain\\Models\\UserPoint.php' => 'ca5fc03d6098a5a4969e7d577c176242a8dd640dbeb2e4f0baac6ef134df1ec4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Domain\\Models\\UserTierHistory.php' => '676062b2fcf4f76a3a187fa4c4762f8fd8f708cb2e73e95b37165610738f3873',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Domain\\ValueObjects\\LoyaltyEarnReconciliation.php' => 'c01c986ee542853cfbf297765da5475d27ce3f06d4c20c00ad81c69aecee5e41',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Controllers\\Admin\\LoyaltyTierController.php' => '511d4d80f5ff555fd30e7cffc920888b4fe55433438798ad9beba08637cf1762',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Controllers\\Customer\\LoyaltySummaryController.php' => 'eadce392ad9742b96849d5103cf12039e279c48f0d2efca5c0f9f8733f518020',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Controllers\\Customer\\ReservationLoyaltyController.php' => 'f6b52957eb31dc2370b1127cfdefd10f92ea62b2b46b0f9acf2e81e046a8c7ac',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Controllers\\Staff\\LoyaltyLedgerController.php' => '3984ec3eeb81da2513df3d1deb7a47da9125c5f88a0e0ddc9684d530dca063bd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Admin\\CreateLoyaltyTierRequest.php' => '5804308c106e1f292b462d449db2fe0d65b37570c2db38d3005bba2d2ccefa0a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Admin\\LegacyUpdateLoyaltyTierRequest.php' => '2cebd74248feedf88e27cfdc263e9cb5e5586e6d8482ca738409a68b31b6395b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Admin\\ListLoyaltyTiersRequest.php' => '6794275f66ab788d49991f870f20bd136aefd7f5c90f46f823d04b936bd9ebce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Admin\\StoreLoyaltyTierRequest.php' => 'd044cd5ada69d1000233d73327c2368cfc6dfc5a2ad2e79d2b11d40733961090',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Admin\\UpdateLoyaltyTierRequest.php' => '57c908b4905570e1bef08526e2a6056e8f253c0fc6edc232fc2f166464e0d57b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Customer\\RedeemReservationPointsRequest.php' => 'a830a28cd1c7f0841916a4b6169bdcd299d74fee743754d4f947aff4cd78bec4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Customer\\ReleaseReservationPointsRequest.php' => '642aaf245322c6864133fd96d1cf9efc0bd2835c41698719be12c7d655ac2c1e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Customer\\ShowLoyaltySummaryRequest.php' => '6a0fef3673c43998c66c079608b67da847516764080e6353da86661c0e6a527e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Staff\\AdjustUserLoyaltyPointsRequest.php' => '196916eedb62cc57a81c106b833de347091925c34b18a190e20491005c26f936',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Staff\\RedeemReservationPointsRequest.php' => 'bf5e7d3cf04a1c7d5afa9e4d53399595aa838bac514965d7eed450f39bbdc0d3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Requests\\Staff\\ReleaseReservationPointsRequest.php' => '36f285ff6423ca7a13f66be5e158eeaeabf5e4af7f72299f66c16997c2f75377',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Resources\\Admin\\LegacyLoyaltyTierResource.php' => 'b321455707c23661d9aa3e8688c81f345df8aeaede0f952e60aeee78119a4f87',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Resources\\Admin\\LoyaltyTierResource.php' => '3b5d501aaa6ecaff0cb8c53d976df5458ebec2a79da8fa647d42fa5c04c32fd4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Resources\\Customer\\LoyaltySummaryResource.php' => '417c6ff78ac543a8cb340e6703848e347c133f4623046114a2c36c84b712dd9c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Loyalty\\Http\\Resources\\LoyaltyPointTransactionResource.php' => '7f03bb223dc8d1eba48a61853bd3fb6d5fa7b1fb5c3014864d518f837620be15',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Application\\UseCases\\Export\\ExportMasterDataHandler.php' => '0345a7b494d4702e64d773a0fb698c6c3c81a002a7504d06877c733d62140c31',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Application\\UseCases\\Import\\ImportMasterDataHandler.php' => 'f0e24cdfeab5d4ac084c691f14288d2207b984def440912a7667ee55f91192fa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Application\\UseCases\\Import\\ValidateMasterDataImportHandler.php' => 'f5ef9983b9df636d34ed7f97fe1c71b3e9a220a891a9a0d3e1e4e88e99f9a56a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Application\\UseCases\\Registry\\ResolveMasterDataDomainHandler.php' => '5e901ff054f7ceca687dc6c081930a9c542b5520d39b54f05e15fff65a81fc71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Application\\Workflows\\MasterDataImportWorkflow.php' => '9cf1ca61fb953398b89fe36f67349a173640eb10c7081b80fc2d729775e128d4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Contracts\\MasterDataDomain.php' => '5d77e4a28071f86ebbd2c7b0f64f462e306d09dba1b046ff964a1f0457988b33',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\BranchesMasterDataDomain.php' => '03087a711f75448e5dda76696630eafe0489ef4cdf9c0e49142f12fc11d984b0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\LoyaltyTiersMasterDataDomain.php' => 'fb9b5830473262bdbed68160ffae0cad7221eda4259a535ad248d2965b8336f6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\MenuCategoriesMasterDataDomain.php' => '0a8990106f0156bedc23a5755801a6db7e384e8b540b097cf8e822f9261ae9a7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\MenuItemsMasterDataDomain.php' => 'a5bcdf115c9e07335d429ff22a0e5eed1b99f13082617012b3f8360b7927e240',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\MenuPricesMasterDataDomain.php' => '32e2cabaaf26b6ded9b0f4db07ebbf40f1fe8fe6689a9999f6c05bd88edf64fb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\RestaurantTablesMasterDataDomain.php' => '26bc3dcf8b2f4ceaaa0b037e936271a5dc5ce6952ebdf5f627d77a7a2377eb93',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Domain\\Registries\\VouchersMasterDataDomain.php' => 'eb2c84c7753be37fdb36075a864d510c9d4d7bbe386125bbd6bcaa8f78d11012',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController.php' => 'f376594955793b49e65c5d8a47520ca6f79065fb85924ff97cf6ec6b39177392',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController.php' => 'c5c5bf534cb666a2af7884aaffb0e6f1be131aa3d2b5609a6fb4e962f936ad5b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Http\\Requests\\Admin\\ExportMasterDataRequest.php' => 'e8fe5d0aaf11ed610b7130b4594bf4c5c028422c7fa54b9a7bbfcc42517bfb33',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Http\\Requests\\Admin\\ImportMasterDataRequest.php' => '9509add2fd574b46ffafcb684138e18886a7dce2ea59497b1461c2da6bd2b905',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Http\\Resources\\Admin\\MasterDataImportResultResource.php' => 'efab2c10923df1c8622a1a4c446af05bf5db5abf7e8c45af9656ede042650a39',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Infrastructure\\Files\\Parsers\\MasterDataImportSourceParser.php' => '44ab4673a3fb1d97642150985deed98d1762ae16fc8659adaa3f0333f987ad4a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\MasterDataExchange\\Infrastructure\\Internal\\AbstractMasterDataDomain.php' => '50e598aa4df40e5b2b61d2c4421386abf1f0f3fa568179ab1a178ffb5fceeea4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Application\\Services\\NotificationChannelManager.php' => '76964047e4f0e390436dcc7489fafde8ccb39453518af61b8f2f6fd588f0c4b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Application\\Services\\NotificationOutboxHealthService.php' => 'ebe8eed42ed5dcdfee258f16e0b19b1727d3331040ae9301b964a4d7da183e3a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Application\\Services\\NotificationOutboxService.php' => 'b777ff54e5fc6a6a88c03a58430f8f879f4a47624c486af016ec8d0560d1f926',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Application\\Services\\NotificationPreferenceService.php' => '765d1135c3dd2807608dfb2e4976835e9c005c2a081fa78a31549f27a2b0fc18',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Domain\\Models\\NotificationDeliveryAttempt.php' => 'd1bc0719a6c934978161cbc912219b0e48ec3f3ffce4b79fe7fd5f4172bc053c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Domain\\Models\\NotificationOutbox.php' => 'c0c92ea4b5c7963a94a2eda8535d15c3d699a268089d892f1a56115ef820bdda',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Domain\\Models\\NotificationPreference.php' => '579977c413d5a6afd19b9b932a95d0807bcc4dd06869db375ae1b9e17eb4b781',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\Contracts\\NotificationChannelDriver.php' => 'cedfa20d8724417a7f20b4124637c2ffd55ff688522e7f7d7e2f67c8aa6feadb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\Drivers\\EmailNotificationChannelDriver.php' => 'de91150a8cb3feb327f56efe30c078905b372b94fb20c02ee3a42b1aa6537f0d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\Drivers\\SmsStubNotificationChannelDriver.php' => '2a6e3f6483d7915d555031e22330f03968c3152d141d7e4697cd992a4f0bd049',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\Drivers\\ZaloStubNotificationChannelDriver.php' => '5d14378401097b98a8888ed9a01829989b5beaf5afa5948c8b4145a4bbb44984',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\NotificationDeliveryException.php' => '1142aa997b0a44172d80a5f710b215685f1f610bd83e1527c554da7d03d58709',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Notifications\\Infrastructure\\NotificationDeliveryResult.php' => 'd816a5616f1b4f03c0639bea5ccdf8b8b7a4995523368b5161f2495831d43162',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Application\\Queries\\StaffOrderReadService.php' => 'a48373cc4184bf3d8e1e83bfb97d263f0d0ae50b9967732d431967bbad2c9dce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Application\\UseCases\\OrderItems\\StaffOrderItemLifecycleService.php' => '14f8c5eec495fbb132abd1d97a39a83deb7a4b6e2d2253511b0542c0ac366406',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Application\\UseCases\\Orders\\StaffTableOrderService.php' => '79391e7725b2d44ab348d9f39bfa7c46c319be0e47b370ff65bee1d40ce3ff1d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Domain\\Models\\ReservationOrder.php' => 'd01f7ce81dafa7834356046461375d3635ddf17ad3c334ab591c9b19b2664438',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Domain\\Models\\ReservationOrderItem.php' => '39ccd3f8ea4c9ca11045b39a7e525a0632492cbdb3aa6b30fa1d77dbea8e4d1d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Domain\\Policies\\ReservationOrderItemStatusTransitionPolicy.php' => '39fc220b3c1bed8b3f543f0a842bb1a96c6deb7185e2dd62a4b6e645b09918a9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Controllers\\Staff\\OrderItemLifecycleController.php' => 'b9c7620c78ae86a0fdc8555a02626d56587b287409bbce62e65cc011f143bceb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Controllers\\Staff\\OrderReadController.php' => 'ad3bb5b130909f8967c96de5b9b6442516c2e817e7f2c6291d09f6f09204ad39',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Controllers\\Staff\\ReservationOrderController.php' => '7874682b14035fa416754e92bb937b6ac38cc76c4cd0f2e666e63f21787e1711',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Requests\\Staff\\AddOrderItemsRequest.php' => 'e7d4baf8695ab9718cf43058642521e336d0bb37ff2fe4df7dce30199dfc66ae',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Requests\\Staff\\CreateTableOrderRequest.php' => '4b9daad0cb503b829b848afb1a244370f94dfe33e47b3dfb91b8dae564e3f933',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Requests\\Staff\\UpdateOrderItemRequest.php' => '0505b415706904c27d5e91e456d083f10f092b9d2d5c5ff06d7f2190e3880341',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Requests\\Staff\\UpdateOrderItemStatusRequest.php' => 'f13d662a4c4c8a60a4f50d7190476a76e26136d8d20c6076f1f9ac3fe733b803',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Resources\\Customer\\ReservationOrderReadResource.php' => '9f2b9b7fc4a8a4f28eacf0f73aea1446394ed955fb2fba4b5f721edcc3839fb6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Resources\\ReservationOrderResource.php' => 'ecde0b443c4297419375e0a8b56d23db717a8390da1265b1ed4ee559cb7a33a2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Ordering\\Http\\Resources\\Staff\\OrderReadResource.php' => '4a4f8b877c99f29464bfb221d66b7527c43a29101342bb0ba830e7c14cc9917e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\Queries\\StaffReservationDepositOperationalReadService.php' => 'a20cdb00467e1fd8389414629d0dc1366d1a391c8bfe03c7c789643a44019ec5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\Capture\\PaymentCaptureService.php' => 'f6844e4849d3e4e2e998159e2db3371ee8913c2aced456038a93531a8477539b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\Capture\\StaffReservationDepositService.php' => '9790af1da741d6f9ef8be6cf490be69e8b2dc607edb6afddc3d4831a69b74abb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\PaymentSessions\\CustomerReservationBillPaymentService.php' => '38fd64dd5aab64b79bcf5023275cc280dc050e11402477f30623acaa3a61a5b9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\PaymentSessions\\CustomerReservationDepositPaymentService.php' => '290c60808469dfb93c96ca05d5cb8cf9774923b0acd100200b40b0af63ea90ef',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\PaymentSessions\\ReservationBillPaymentService.php' => 'dd57ad73a636149e6733dc6970ea84e45339cbb7ae2094cbf67cfdd886e4f266',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\PaymentSessions\\ReservationDepositPaymentService.php' => 'ace4cf4a52a45d1e9d9776c6b3a99934fa40de65e96e1020b31380ac6f3e4367',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\Refunds\\RefundExecutionService.php' => '1bfc98e8398e4319303ca219fe01e4e3d807fbc1bded0b6125bc7f8bf6d58729',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\Refunds\\RefundPlannerService.php' => 'd6b2e10d87e8cec6d49334835fd1c81c2d553ff37f1bf47f46f42650226cb9f1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\UseCases\\Refunds\\ReservationRefundWorkflow.php' => '0ef8765240ae90d5b7bd5a53cb83a64ab2e08b6230f62e7dcdba0d1db0c6eb8f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\Workflows\\PaymentWebhookIngestionWorkflow.php' => 'b7ffc53c8b196aadc248f1cc6bda88eb0450bf1c13c2f8c781088afb9a53230c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\Workflows\\ReservationBillPaymentSessionLifecycleWorkflow.php' => '54f6ce81694e9bfc34a9786d6350ad74462ebe32bbfd2ec92ff7ef859154848e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Application\\Workflows\\ReservationDepositPaymentSessionLifecycleWorkflow.php' => 'cc9936be62754a3d39626456893e7060ee9efa8a8ff0e733fd52f89eb811348b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Guards\\PaymentIntegrityGuard.php' => '9fdc95df8254cdf2645b8cfdc98c54a48e4ec9d02e370b21a45bd1013dfb3915',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Guards\\PaymentSessionScopeGuard.php' => 'c8091d002d49ae238a7306ef7006a5f323f6610648a16a57e18033e4482ac570',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Models\\Payment.php' => '16e1a15786328d545dae9ff84213cdf63c9a80b634e7855f938b3c4a985a143e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Models\\PaymentProviderWebhookReceipt.php' => '27b1efba1d1a21d3aa18bff40e2401075f75c522e1cb46c19dd1db53eda97e67',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Models\\ReservationBillPaymentSession.php' => '838f1516e0416e33cb1decf045182bf25d7a231471dcec8cc0c26f67775e9648',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Models\\ReservationDepositPaymentSession.php' => 'bb06f88bd0a35ea5447ae59d9a412b7917336895c6b5528fe0619939e0064f31',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Policies\\PaymentSessionStatusTransitionPolicy.php' => '063de6eb44afc8f1dbb0f1bfab88c2cee1419823f4a311618c75ec1efee9a2d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Policies\\PaymentStatusTransitionPolicy.php' => '0d107c5e4989a8ee01de1261b81bbaffe54a79a78c8594c64006b004d1e6d1fa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Domain\\Policies\\RefundAllocationPolicy.php' => '22c80997d446eab234afb9632f86f903d32dfa4287cbd9e78fbde2474c206ad1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Controllers\\Customer\\ReservationBillPaymentController.php' => '7e0f6a117eed3ae3e948b14c97fe0c627053c6a95f55cbcdfb77f9725a2217a6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Controllers\\Customer\\ReservationDepositPaymentController.php' => 'ea49bdce3e12f41304851296f026febf09ced133227eebc941e403bd68f93cab',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Controllers\\Staff\\ReservationDepositPaymentController.php' => '7587591d8b2a15dd5889b066b213692a3ecdac753b58f371975c3a98b6e2684f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Controllers\\Staff\\ReservationRefundController.php' => 'c4259bd1d76d7ae100c1bb80cb8a649c65325da801bcca45cc3d429f9ab22edb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Controllers\\Webhook\\PaymentProviderWebhookController.php' => '6802d2bd722bcae7c42c44c0a1ce9f9b76f501ee63f5f42b74a5b5a229319c4c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Customer\\MutateReservationBillPaymentSessionRequest.php' => '5e2dc0c6501d68df6662c3ba7e526a63d6223283805d91168e4241d682da1c99',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Customer\\MutateReservationDepositPaymentSessionRequest.php' => 'f46397af84c7299658629b0410ca7d3fd7ebfd98ff14698661477250b8627686',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Customer\\StartReservationBillPaymentRequest.php' => 'db0ec32232325a77d4cd922b1405012ed7ce29be0a96a323d8086fe07b200079',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Customer\\StartReservationDepositPaymentRequest.php' => '765a523ebf56a026d7b0a66aa593cee1059bbf7c354d30b44410b33a8183cc98',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Staff\\PayReservationDepositRequest.php' => 'aa08aafd99871c083a44ac62692c68bc135903e897b36ee23c2d7aa776714144',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Staff\\RefundAndCancelReservationRequest.php' => 'd6d95257d75850cd3153297ed24a61d2f00d7e22abec7b58f1572ed95b4a47c7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Staff\\RefundPreviewRequest.php' => '381515d0297dd417c7dacbfa6d26ff01bceef462dd957da9856ed0ab3eed166b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Requests\\Staff\\RefundReservationRequest.php' => '979ba9ff7bac4c95909e7fdb8f323dac117b5b767bfa7bb16ec4fdb0f309e3d1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Resources\\Customer\\ReservationBillPaymentSessionResource.php' => '93bd498b974e6e2caba5044414ee81225deb7204f9bd7904c33469fede210257',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Http\\Resources\\Customer\\ReservationDepositPaymentSessionResource.php' => '9b92ac771fbb392c6858a7e29195de49016946d0be045e1a5547acdf7cf30b27',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerBillPayment\\CustomerBillPaymentProvider.php' => '2bd7c984b9550d1b4e057f6a9362292e67ddc12503184f40b8907c03cc6405a2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerBillPayment\\CustomerBillPaymentProviderRegistry.php' => '0a8254b9f2f4763a0f6b9285fba5a891bf8a7c64eec2e8cb8606f126f477520e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerBillPayment\\GenericHttpHmacCustomerBillPaymentProvider.php' => '2701b844a9d8bb46b859a615b5b4ee11424965268af45941fe70ef1472311f00',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerBillPayment\\SimulatedCustomerBillPaymentProvider.php' => 'e04dd3931a6ae5f20994069ad8857795625dbc61d36666f464b9be5af1a826b2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerDepositPayment\\CustomerDepositPaymentProvider.php' => 'f4b059a4d1369c05dd5220baf84b0d6a6dfc685278a71a7505a6e06798c9a1b4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerDepositPayment\\CustomerDepositPaymentProviderRegistry.php' => '25a9864e0f1416b655c72763cc11f88fcb3aa6160f338f0ed7ba27f361a2c4f8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerDepositPayment\\GenericHttpHmacCustomerDepositPaymentProvider.php' => 'a1a12b7d62f415fb9cb0aa2ba3d7b3b2c57386006e102be5303abc6fd80be5fb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\CustomerDepositPayment\\SimulatedCustomerDepositPaymentProvider.php' => '1ae1fd56f67af156f30818a31322385d82b5c9d2210bab41f1d004936c2a9883',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\Drivers\\SimulatedCustomerPaymentSessionDriver.php' => 'e5290586d6e1944e75002fab619567647df1d66894ef5cf64b154a9e08f0fbc6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\PaymentProviders\\GenericHttpHmacPaymentProviderAdapter.php' => '193e5a04eb66792936db9840dbdf24ffa13228bedf75da5d390245b5198990fb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\PaymentProviders\\PaymentProviderAdapter.php' => 'c998acf4ede314c718867e08284f64ce81441fee25bdc7ae5cd69384d4cbbdaf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\PaymentProviders\\PaymentProviderRegistry.php' => '8c40888fe4b17a462ab2208b0640eb3eac2816beef0c56d9bdfd997d643feecd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\PaymentProviders\\PaymentProviderRolloutConfig.php' => '35fe06d207923a72dd2e137839ffd664ffff5e366348ced88014361c4a87aff7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Integrations\\PaymentProviders\\SimulatedPaymentProviderAdapter.php' => '9b5ba5caab32ef9479e561ffec39495e86ed69cb93082db2db6a3bfcec345650',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Payments\\Infrastructure\\Internal\\PaymentProviderPayloadSanitizer.php' => 'cc367597c18d54bbd6377febe7f748b675171d22eda50de748251902fecb6ee4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Application\\Queries\\Audit\\AuditTrailQueryHandler.php' => '32dcb2e757e3f96e8fed8326675d892e674e28c1f88671f327a4fd7d4d6cdb21',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Application\\UseCases\\Requests\\CustomerDataExportHandler.php' => 'e1ea6c78f843e57c802cd7fab0129618a0b203d1e510f58c19fad7570fd3e207',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Application\\Workflows\\Redaction\\CustomerAnonymizationWorkflow.php' => '9f3c0dc8336bd13eeb185a790930e7ef2f122769f83457f6ab3d9f6d576d0b51',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Application\\Workflows\\Requests\\PrivacyRequestWorkflow.php' => '3038251e97be004787968cc1259c916985779b2f967ec2451d31595bd8670668',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Application\\Workflows\\Retention\\RetentionEnforcementWorkflow.php' => 'ed972b04c90fc48e2acfda54172e6cbd2f4e841ea56b6bb9dc08677c8ebdf8e5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Domain\\Models\\AuditLog.php' => '710653d12e25dabbb17bf0834c746d2d35d1f166f5b10a19cc17cc17c42b2b1a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Domain\\Models\\AuditLogSubject.php' => '3ac478c378541c19c9f0c23ce35489a2882916e429fdaee0bab0ea610cbef3ed',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Domain\\Models\\CustomerPrivacyRequest.php' => '8cba7bd820404329662541c61f3ca119d40e75f6f5889d687c54c001cf5f85f9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Controllers\\Admin\\PrivacyController.php' => '39c93dd21a8922c92d1601250fe06c75d8355d9284b9f19041ff9889fa6eaa1e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Controllers\\Customer\\PrivacyRequestController.php' => 'd9b39acc4b094901fa47e2c35efbd65ede7880784e245b959e0953e12ca184f7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Controllers\\Staff\\AuditTrailController.php' => 'd5e78eeeeb813f5a5de7689f06d0f3f97f2ffc985b9ebdfff42e363d6df30fe8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Requests\\Admin\\ListPrivacyRequestsRequest.php' => '02d8b076fbe38ca122d341c33556aeb41da410f394159a2ec47da8f926eb3c55',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Requests\\Admin\\ReviewPrivacyRequestRequest.php' => 'c50cf437bbf9e5074968d0bd90badf42f0f36a8f8c545d70acf4d612225a3d94',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Requests\\Customer\\CreatePrivacyRequestRequest.php' => 'a7f505b7720c59c632c6a617a8a5b38edd223b3b8353d068b69c1264e3bdaa64',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Requests\\Customer\\ListPrivacyRequestsRequest.php' => 'e9b74638f7e87d66103e5728b03bff70f01c4a028fe509a458cbf94295ab6e1e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\PrivacyCompliance\\Http\\Requests\\Staff\\ListAuditTrailRequest.php' => 'f18f627a0c4980358b1dc0b9dc25b6c338b60721c958cb90ab3810713e5b4af7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\UseCases\\Benefits\\BenefitRuntimeSettingService.php' => '49a7464122297c3d9c5e6846f092c2a862172b311ea24d62f55afaeff75d81f4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\UseCases\\Benefits\\CustomerBenefitsService.php' => '86f4c653c00bc0674e54e72804d710ec7f35ee02102f1bded6bf974d4dd3fe6c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\UseCases\\Vouchers\\ReservationVoucherPreviewService.php' => 'f5726b458b09d389f1004beadf3c9fdd4159a8bd49d6962759e4c7ee63241e97',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\UseCases\\Vouchers\\VoucherManagementService.php' => '9edb09d63021b3124e47368ab8e7ee0bfc8ad149035766d58f61ce4eceab3fa4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\Workflows\\CustomerReservationPromotionWorkflow.php' => 'c6a62189dd85a585be29662bdb5a98c60b99849abd23d314f604cb1aa55a1a56',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Application\\Workflows\\ReservationVoucherWorkflow.php' => '0fb208ade9a44366a0616df8e0dca9868ba176fa217da9fb14d2a72dac80e73b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Domain\\Guards\\VoucherUsageGuard.php' => '361a115da39377da9f8a6a80b8f975d79389767c33612b2dc3905e97451848c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Domain\\Models\\UserVoucher.php' => 'a497a74e62307575c16be5a9f35210633c52faea0b164e6dae1ae47ed58f5370',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Domain\\Models\\Voucher.php' => 'b9720b811d447ab3b387d30fea2a135aed546dde54f419a0b08ad16287c067fe',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Domain\\Policies\\ReservationVoucherLifecycleSupport.php' => 'de5530de8e45d28e94287317007f7a817632c0f727816475f5b0d05e67661eea',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Domain\\Policies\\VoucherRedemptionSupport.php' => 'd6918492350b8fbfadacdfc64426ceda9c2a2b5141c4c63fdba55c68edce6ccc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Controllers\\Admin\\BenefitSettingController.php' => '7d9f0b31323f0ddf7c5e9e0b60328502af49e3eec1a1f0f73e7a15378bc10ea3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Controllers\\Admin\\VoucherController.php' => '6ab826349d0461992945643092f6cd4f73b894b7edd53efb5d01690a7aac43af',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Controllers\\Customer\\BenefitsController.php' => '2514198a839274b7aebda9f9b5df582bb346831450c767da46cf9a60a4a2cab0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Controllers\\Customer\\ReservationBenefitsActionController.php' => 'd5019b24a9cf39c65aa991fd86c2c1edf7227e982dfe5c33b5f0d20ad0a43065',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Controllers\\Staff\\ReservationVoucherController.php' => 'd7a07ce64ff397ddb91ed6d9aa3d265c004c7fe6e0d46aeb1179a28dbd16d3f2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\CreateVoucherRequest.php' => '5fd1a5c6d74e9efe3a05a08db0b3526019dff0d0650514b93a30a9dcca13c6c8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\LegacyUpdateVoucherRequest.php' => 'c241575e20f34f118881dc515dc661d55b651a2333190632c3437d13a23b14d5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\ListVouchersRequest.php' => '7e1366c21fc9e6b49495c8e865d21274cbe873b472eecb3a5c85b953aa9601b8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\StoreVoucherRequest.php' => '318e98bddaa98ab98dbac0943ef9c75f3d18d5b6e6c0703bb444d3b1ba82c4e2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\UpdateVoucherRequest.php' => '87c7c2db11eba66521792d113b27038fe0a83b40bc67337fb34cb1abb1fa757e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\UpsertBenefitRuntimeSettingRequest.php' => '50e05bffce55bcfcd4863ab1f2b7251414a2b676b699e09698643c5fd298f7a1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Admin\\UpsertBenefitSettingRequest.php' => 'd368b59601976498e2f7de69a87a3c24f6fd65f7a27a34240275afc384c6df23',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Customer\\ApplyReservationVoucherRequest.php' => 'd0f11f1a8250f7ace4d15b78840658173cbfa537dadece9e1df5aa75a2b7b067',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Customer\\ListVouchersRequest.php' => '334d776bbf5cc1901b083765cf297e94447044d7387dad9445386a7e671c105a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Customer\\RemoveReservationVoucherRequest.php' => '59e2c9f6f65a338f4fa5e022410e27cb09a42b7d7ce5a1ab20eb4decfaa4107d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Staff\\ApplyReservationVoucherRequest.php' => '6734f31160804cef48ac7e06a774577c63e6a73718ace43afc78bda6a4a96ce7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Requests\\Staff\\RemoveReservationVoucherRequest.php' => '8121068a81130b26996199c659c6dbb418b5d2e9f0015d1ff7f96bba9742dd0a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Resources\\Admin\\RuntimeSettingResource.php' => 'f37595a4b38c66442fc412b2be6a04227bbe3cca4a39b189468f65a19005ce13',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Resources\\Admin\\VoucherResource.php' => '60256008adc592947ba22d9f61fa208a9239eec60474d76575114d1f63a4c1a5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Resources\\Customer\\ReservationBenefitsPreviewResource.php' => '3b92352ebcec5d23edb4b1244908b1689dbeadd2be25a0c6266948a28ef58d07',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Promotions\\Http\\Resources\\Customer\\VoucherResource.php' => 'ddea4fa3154e61138726260441b993342f0fffa8dcecea1b1c48d124374b56d9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Application\\Queries\\Inventory\\GetInventoryReportHandler.php' => '9dc400d6eabfdfc5885f897e2be1472f396fb49fabca4ef93255ab388f22a7a0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Application\\Queries\\Operations\\GetOperationsReportHandler.php' => '63f0260a75e4e5dfd4c4ec90a6b83b7d4bc54c567363f9cab162413a94122068',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Application\\Queries\\Sales\\GetSalesReportHandler.php' => '7debaeda06aaa8b23ea981ee247a61546fbf4cab2cfd6b4ef41c507f3c61103b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Application\\Workflows\\ReportingSnapshotWorkflow.php' => '3393aff54eeebe523b2ca767d16d1f2cad8fedabb41a6a384bd48f95f7cc5598',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Domain\\Models\\ReportingDailyInventoryMovementSnapshot.php' => 'ed7082637344313e420d0ec3a337e323ae6a9ffbd62bf6a8ae41e2bf4a817204',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Domain\\Models\\ReportingDailyOperationSnapshot.php' => '30866c482d875cf7344b9d23926257ec2ecf545c6b8a0d8502cbb303f7f1dacc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Domain\\Models\\ReportingDailySalesSnapshot.php' => '9915822a939d441ef29ca4b6b787edfff61068eb096cabd56b7bac9817883da1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Concerns\\BuildsReportingResponse.php' => 'bbadb12534b8f4daadc9b9c7bd1659f368bf638b485f69b604751922b012a52e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Controllers\\Admin\\ReportingSnapshotController.php' => '07eba04407c609882dde62c93689a9940e9255d8a00e8aa24e16b09baf0f77d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Controllers\\Staff\\InventoryReportController.php' => 'aef0a29db65e1ea1166490c94b71d58ba7e61336ca26695c804389ea0e041cf1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Controllers\\Staff\\OperationsReportController.php' => '1601f8e45b7540aa200e9f4d003bcad6971535abf5f8e456be920f980333271f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Controllers\\Staff\\SalesReportController.php' => 'a05b1f051d7243294d07b8930fb87db758e88c6b37da9f6c0ba628b4aa2c0f6c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Requests\\Admin\\RebuildReportingSnapshotsRequest.php' => 'd7b5e6a72195c0d9db486853e30d188c824bf07562b17210b2f28197cb770d4a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Requests\\Staff\\InventoryReportRequest.php' => '2bff60aac0eebc88ddef06923a4b25ca688dcf6e5088e93ba2c4aa43c2b96cac',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Requests\\Staff\\OperationsReportRequest.php' => 'a05a3fed8f687e1d6f8a6fb47b0a09b292e929d19440bff6719d03a104457f3a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Requests\\Staff\\SalesReportRequest.php' => 'd5a48bd8f47335e040367b84b000f2c5f251fe8f259f95b69e7ba70fece5477d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Resources\\Staff\\DailyInventoryMovementSnapshotResource.php' => '99e6427d01df0b7d6bbfcfa0929540f6b3903205eaaf568beb63d695d06f109f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Resources\\Staff\\DailyOperationsSnapshotResource.php' => 'a2b5d3fc6c941a0cbba2b23271cae2b1cddab76da6bad59bb14daed307537b17',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reporting\\Http\\Resources\\Staff\\DailySalesSnapshotResource.php' => '9f3f1ebc998c3302e61240eb9826543784bbe67c9fb3fc3959c480703894d1fe',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\CustomerReservationDepositIntentService.php' => '06d5d3631d12fc5f7567374148b082b7485feb1dcba4982dc0ac0f228c88bb4f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\CustomerReservationDepositService.php' => '111e606ade4a81e54e9e0d6a4767cfdfdbe37921d324efeb1062b41b6a53f7d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\CustomerReservationPreorderService.php' => '3202623e0a7bad0c15af97d68eb61a02eff2dea1e10b3efec1ded891c7348362',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\CustomerReservationSelfService.php' => '84fad17227d33e1204f7a93f6c279e45df708f216235126e5998abec68c02e93',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationCancellationService.php' => 'c24f6b8492a8cf3665a943804439ec0909e195225aff0afd87fe1c097deb8336',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationCodeGenerator.php' => '8b746fc943027fd47ae14b6abc598718fd01c4b629985b62caa2f58b597c0607',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationConflictValidator.php' => '7198036f24a1a9b17f349589f624938a29635f8e6d1aa122970dbc7ed811ddc6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationCreateService.php' => 'cefa090058b115a4096768e7cdbf9eb2f25fe79d8fb792b761e1fa7432c7e493',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationDepositReadService.php' => 'a80d6782c3dee424163b87e1f020ef3ee1684e0cd5da372c0b7e839aae5091a5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationDepositRealtimePublisher.php' => 'fac61df7fdf58ebd4f7c81e28d0edee19718e829a885794fe869b1e32e2a4081',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationDepositSelfServiceStateService.php' => '39225eabcad21d6e6039f5b0194baa621f255ceaefa97a3f5478b0d1e783713f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationLockService.php' => '0fd19596fe53804f44a43adbdcedde83219084a3171dbb3f9e8982bce1075737',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationPreorderService.php' => '9c1d347d79803368b5fbe4dbacca2444f17405a191f69c9541ab818cd40f6913',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationRescheduleService.php' => '543eb21b4288fde8a6e29d8c28e10871d830479e6957cf9c99f18f5a77e84896',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationService.php' => '6ae15b41c2c8d7bdf215d1e237fc96f8f240a050d5ea353fac83d16a0d1b8f65',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationStatusTransitionService.php' => 'f5831dffa30f9f415bb55860349903034d883fc7b17a49d54aa2723ba3def57a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Application\\Services\\ReservationTableAssignmentService.php' => 'a9aa5a0408d0024d9ac49cbe36ee842b3a358744f524558fd60f6edcc832745c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Domain\\Models\\Reservation.php' => '138e7384680da6bd8d87ee0620d88e9459248d8eb45d6de68f7dc593bd6cf0f9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Domain\\Models\\ReservationTable.php' => 'f468d625cccb721fa92c1cf08241a68b7de0b3769dc35933577b7d3d1cb47b96',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Domain\\Policies\\ReservationAccessScope.php' => 'fbc21beb147e9f6fb871d1bf79326299d2af6a1fa0aacb953d9ccdd68329121f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Domain\\Policies\\ReservationStatusTransitionPolicy.php' => 'dc92a00bc188f22e1c87c78956758295ce60272706d82a596783a83ecb69d72e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Domain\\Policies\\ReservationViewProfile.php' => 'e88db4103f10c11e940c088d60d52b1ef1b12366c7bc56b703cdfa09e93d9d71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationDepositController.php' => 'c0714ffc748e2bd78d7d312ea35ad62173a46d890c09af0925951aa3f2ad05f2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController.php' => 'bb8810d0cb9d93edf3fd91b8a65aa6406f26f4481e7df0fdd333e0e71ab078ca',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\Customer\\ReservationController.php' => 'a87db83ffa4b46f5ddef86e777823a04196311f45d644ea2ba25c078bd67a1b9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\Customer\\ReservationSelfServiceController.php' => 'e1a556ecd8a1edb61769a31db1ea1abb8245b6a60056a7e8c62d87a34723a2b6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\Staff\\ReservationController.php' => '246d4122ffb5aead7a9b41eb1af9e57871c0e0241e68f2b3e3e2c7c99f1de53b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Controllers\\Staff\\ReservationRescheduleController.php' => '8d3cd105892bb58be41576151205179adb6b360e087d7a0c62a56a1476548e3a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\AcknowledgeCustomerReservationDepositRequest.php' => '4e09d2f9a5df6ff9e12f300b85b11b7888a4da4e820afe452a733e0437670f70',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\ClearCustomerReservationPreorderRequest.php' => '893db1cfb09a45401e55872971bd621f977211c784c353bf7881f242bf53a8bf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\Customer\\CancelReservationRequest.php' => '5221c1b48296fcf348e708e2b4c22a08fcbbb8e965af3434710ec9ccf64cd2ce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\Customer\\CreateReservationRequest.php' => '75d8377017876efd48f9fb5d01b96704791d387c99ced711d0d691bba1a8a159',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\Customer\\RescheduleReservationRequest.php' => '7ffa9fda045b5697f7763897644cc1bba6a58d10347185f69f53b48f30a2b125',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\ListReservationsRequest.php' => 'a10b9e8dfabac5495a6e2cdbfb55121619a6baaa6ca3f60e8d36825492215898',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\MutateCustomerReservationDepositRequest.php' => '263ec69772d1080612349c6eb23cae3bf7facd437a18d28cc6151e791a60ecfd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\PreviewCustomerReservationPreorderRequest.php' => '8712a60e98795145583f546a08303623fd0cd32d9590171f559de57edf2546ee',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\ReplaceCustomerReservationPreorderRequest.php' => '30294f7b815bca6a327a07a3af9dfd25d6117209e45bc4f3ecb5fb1723966e86',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\ReplaceReservationPreOrderRequest.php' => '70ca31fadc6ba1ad5456bf743338eba7b3a9b723320e04e2b957519eb51457f1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\RevokeCustomerReservationDepositIntentRequest.php' => 'b4038531920a2faf3b28f88a4674fd59a703a0e4f4ffbf96d6327ac50d31028d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\Staff\\RescheduleReservationRequest.php' => '806fce2f36def690b16411d1f42df24fbf04db9edb756649e3309fc36e7a257e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\Staff\\UpdateReservationStatusRequest.php' => '9dec9a034ea671c532c5922c93b00d959fef31693402641bf0009d3173ee7fc6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Requests\\SubmitCustomerReservationDepositIntentRequest.php' => 'f3a767cb6aa8e2294a35e21257f746754053c5d2ea48306955d7da7d2230dcf2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Resources\\CustomerReservationDepositPreviewResource.php' => 'd92fac1f7509e1877f90333e3b5983a943cf0b3a4364c4de9e74f30624e225f0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Reservations\\Http\\Resources\\ReservationResource.php' => '82d5560ac6114a861755ef64497d75a300a2f2db5275681b2edcab278ce6fcc3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Application\\Services\\CustomerWaitingListService.php' => '9956a41be67df10f13f6a32a8a201109964d5d510600363ea11d09b20edda008',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Application\\Services\\StaffWaitingListService.php' => '8e5c92ec733a71e357ae6f3ef4f91066957eeac70de0de66179f21d6cedd7582',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Application\\Services\\WaitingListInviteLifecycleService.php' => '3a9621340b3cfb91ce3434a58b2db165d71847b1a14b167000761ea1a8210e1f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Application\\Services\\WaitingListOperationalOrchestrationService.php' => 'f26d83341c5bd073242691ebe8eefea4120d353ad195c3401901e8ad09d311bb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Domain\\Models\\WaitlistEntry.php' => '7ff8d64b772271524a916acb853d6ca98fa23a4cd0f270c36fa9e96b7c6ad53a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Domain\\StateMachines\\WaitlistInvitationStateMachine.php' => '3ac683ff4f4494cc9ff460f666ee91dc8e8b68da80f714e951d16fce6e0ecc7a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Controllers\\Customer\\WaitlistController.php' => '9c98a6e57e590ba783403fc20cf80239ac53baa5f5d75d34a4d4b1dac375873a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Controllers\\Staff\\WaitlistController.php' => '94a420b0abe9593cb0e22cb6192a0ecba612c6d733d88d2f2ee048409edbf312',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Customer\\JoinWaitlistRequest.php' => 'a7ba8a4212b97a5402cee72acb89a6ede3927bd0486668a3de529fb83b2ff08a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Customer\\ListWaitlistRequest.php' => '326a7d3ae17e8deafd84eb6530b92c4ffe7217775347a5bbfe506e06a6af27d4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Customer\\RespondWaitlistInviteRequest.php' => '97dd9832da363abddd3ebfd1c35f92b7b35a40d04c03266f18cbc025f142397b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\AdvanceWaitlistRequest.php' => '923f552427bac106e91303b4f8ca8fcee3d54314bb5e85ca952626b279bd5832',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\CancelWaitlistRequest.php' => '6bd8bcc72f4e3ad7736f1accc409909305b91087d9e3287b070a81bb7f775bc5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\CreateWaitlistEntryRequest.php' => '2c6630a7de82c2c821fbbdf4f30de0de08646ae5a9af7f1679b23eb0d9c88ef3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\InviteWaitlistCustomerRequest.php' => '429047f55ad35a33e010ac3b20f26b77f77b5a244f6191faef8be0559b63b369',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\ListWaitlistRequest.php' => '252a5ba6301df921cc89eb2de2880a1e74810bb67a319dbaab6071fc98566d47',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Requests\\Staff\\SeatWaitlistRequest.php' => 'e14010c25efbd3f39c843b34949256c834c3466335305c8e14775e9101a2c313',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Resources\\Customer\\WaitlistResource.php' => '0f83c1a724daf48983455524e35a7848bfa73904fed098e6cddbf1f24c21a801',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Modules\\Waitlist\\Http\\Resources\\WaitlistResource.php' => '1caa1b4182c16ce78a53b52d4fa88352d737ac43522a2c719c2b46938bd40191',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\ApiArtifacts\\ApiConsumerArtifactService.php' => '7005ea0e9fcbcc11f7f00f78df9631018fc72376ec64a5cef0799757171f515b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\ApiArtifacts\\ApiEnumStateArtifactService.php' => 'b5ab5dae0147270232c0977a12dac638c8483b0c0a16543f8a21ca5b12ea670d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\ApiContractMetadataRegistry.php' => 'dafc3a242bdf263f31b60bb73c34808c28fe880dfb20deb0202e2f7ecbc13cb6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\DatabaseContractInspector.php' => 'f796de3ea31544eafdeb24fa163820681589e5b1b8b7b89cc54b65c2e4fa9773',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\FormRequestSchemaFactory.php' => '9976d9057e0b1c6fc5711e8763f0d5f37e20cb5972e4c23f2c8ceb34857a2500',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\OpenApiSpecService.php' => 'c87f7fb0c6cac005abb076e4055b1f9a421961d20611526e379d9d3763bbf4d6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\OpsGateArtifactService.php' => '3ec48aca0c8e86dc60ea5b2fc26c180ea2b64beb793003600d0da2cdc755501c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\RouteContractReconcilerService.php' => 'aaba3f9b2f413811122a2c2882c7dfb451261fbe43c88b9b18f0d3efe779459c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\ApiContract\\Services\\RouteInventoryGateService.php' => 'fd3dfc1b3aedfb49ceff5d08ef2a4d3efc27634b0637c3aed166a506b1b5b531',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Backup\\DisasterRecovery\\DisasterRecoveryDatabaseProbe.php' => '5f36c175d6126902e1c57d72c47ee691f10f0a186dc11632d300bf4d7adada59',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Backup\\DisasterRecovery\\DisasterRecoveryDrillService.php' => '2073f9b5ac39f92539586ded551bfcd7759f519c164d0b7b5ceff3c9c4c9ff25',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Backup\\DisasterRecovery\\DisasterRecoveryProcessRunner.php' => 'c3a1a25e730164efcd08a3a7477f823ed64ab63a5cf190f2c6ceb5112ff7f2f1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Backup\\Support\\BackupArtifactManifest.php' => '2fa113ff698143629a579114addfaba7a0c792500144d3d4627b47b50de77430',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Backup\\Support\\BackupRestoreManifest.php' => '0f3e6f4b3e717b17fcad7692a7b542aac9239bab3aab864394cee492443ba7b0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Delivery\\Release\\Application\\Verifiers\\PortableSqlSanitizer.php' => '06c97a788312bfac91e0593a0430698044957fc5a9d8d7034fe48d192eb1bb3b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\FeatureFlags\\Domain\\Models\\FeatureFlag.php' => '0b79598f26ee4ec8a9d9447cf1154085c77c4c17c04cc7f4bb92bf6ca797d0a7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\FeatureFlags\\Services\\FeatureFlagManagementService.php' => 'e3bc8ab70e1c19a4107bf98ac2f375f8f8608972846b0f3f1970d784748cb2a7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\FeatureFlags\\Services\\FeatureFlagService.php' => '4d7a5fe5914badf217903ce2b7b4e39d3346c95767cea4ef4c7967b15c42489e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\FeatureFlags\\Services\\RuntimeSettingService.php' => 'fbf0fc85cbffc2356c1b416e98bb97f912cfdbbf6a8da2462e7e6f1c0ce49e2f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Harness\\HarnessSuiteService.php' => '694a793385889257dac858909bbcc16d8fccf685d9aa033a2aadc6cddff5eea3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Http\\HealthController.php' => 'a8a475c73ec6c84eaed719982ed3c036ba170781170c4273508250fca1f0bee0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Services\\BookingDoctorService.php' => '817cbcbe466fc4dc3b86f205888f9af203bffb7874ccfb9bce563f8a0a7a2ef9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Services\\BookingEnvironmentValidator.php' => '913bdc948cea2b6754ac1153c075007ee6c4149e1022744f87d636ab977428f5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Services\\BookingMaintenanceService.php' => '943918643cf9394308447d8dae756ea11cc1b6c1051309833caaa2bc108792ce',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Services\\OpsHeartbeatService.php' => '0c4cb2c0e38340bb1dd01a7e2b944a31766439666b4cb51fb757c1cd55280a55',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Health\\Support\\OperationalHealthEvaluator.php' => 'dc5dcf81221bd22e7c232509078b5eb7329010e4f0b29aa1896bf23a52573226',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Metrics\\Http\\MetricsController.php' => '1c27ab1564f24688837515bb4d70652cb19806bdccb543c81668b15db29d514b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Metrics\\Services\\MetricsService.php' => 'c683374cc648bc23f610f8caca89bcc3963ad474e4ef029c8bc5d5d4b9a091e1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Metrics\\Services\\OperationalAlertService.php' => '61bd0cf06682634d15b396e3a9e8a3b67ad3ca2c290d5fe002dcd838a830550c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Metrics\\Services\\OperationalInsightsService.php' => '73d6ddb5cf6c1b3a70f539525254efdb839c3851fe20f3ac801d3e733fdaae5b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Performance\\PerformanceVerificationService.php' => 'c059c6a896cde3df7279a2983b02a1b9b0b48d32e825650f016243243634b959',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\QualityAssurance\\Harness\\Application\\Builders\\BuildsPhpUnitTestingEnvironment.php' => '0a99f1b8abcf7db720b8c5571be3c7df361a618d7d79f0c7eef91c44ce4648b0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\QualityAssurance\\Verification\\Application\\Verifiers\\StaffMutationRowVersionContract.php' => '42628170a4e85633f1c9498ee33d549062e277c1cc165ce170ced92889a0ea7f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Realtime\\Http\\Requests\\ListOperationalChangeFeedRequest.php' => 'c5b366e7ddbcacc77292a7222c0dba52e89548cedcb7ac32d6b422d3c86d6418',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Realtime\\Services\\OperationalRealtimeService.php' => '5cbdc7f024f399a72864ea73af1ffedab0addf1fd4aa8ac2975e51b11e8de1e7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\BookingDeploySafetyService.php' => '53e7365960787dd73956755a24106f9c8608f47cb686c10cbbdd51c79ec1fc52',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\CoreOpsGateService.php' => '56e2a35c3660a67ec2382b83be34f1851baef27629ce84a75ef77bff39c07214',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\LaunchReadinessManualEvidenceTemplateService.php' => '4059f1a0caba80c88f9273da0097b64afceed0bdd6bd4128b1904bc5ad2d203e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\LaunchReadinessService.php' => 'edc539d1a2a66dff278c1e38a9ac114839e8c8ab8f570acd91517894a3a77b5e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleaseArtifactManifestService.php' => 'a7399ee696fa9716915ca7f1579d1848c5bcbccfe86d4157ac44ff0e29b3b020',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleaseArtifactNormalizerService.php' => 'c4b0816418b5a30d998216ff8e9131ec05ebee72a815a0f46f784a20d276eb04',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleaseBuildMetadataService.php' => 'f5823bc51125d2a2351f3eeed19e48e171799aaf4d240b841cb2731be2850800',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleaseBuildService.php' => 'c94a1b99f2eaead2c633d4f4ce5718c1d8597e5b04c820c4ce61d1198e0eba6f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleaseLoopService.php' => 'c77e09127a1942ea7b89e3c61c8d21b21a9285c491c2ebaac6b14d9135ed53c5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\ReleasePackageService.php' => '7c2476916265169877efad889a41c9b5f0176ee7a7b24a9a2ad6ed8a5faa6f15',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\RoundFiveGateService.php' => 'e9990669e8693464659569e376c69d1ce2176ff7159fa74b24e57498fb313e72',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Release\\Services\\SiteBootstrapService.php' => 'ac90bfee1a91a8254a2f10501480eebb747d5df942f7676c53b37b8a54586a6e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Uat\\UatScenarioPackService.php' => '2f6cfd4da130bb158d174708ac8babcf2daa058384516cac4a76c3d720fffcc7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Platform\\Verification\\VerificationSelectorService.php' => '7676182455bb9ea1dd71f414b471380bebb232b9d13a72894061fc506ec0d4ee',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Providers\\AppServiceProvider.php' => 'f5654aad3faf085e5e2257abdfb44754254a7610a88146926779c8c3ff9eb347',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\SharedKernel\\Money\\Money.php' => 'ed1de7ac8f803352e952e2936669c5bc4d34f85bda514bed17662c17d079ee96',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\ApiErrorCategory.php' => '7f0f00e1ff236ec5cfd7b6dbdcd6e86a19dce25626409ad6744344f3d2b13b10',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\ApiErrorResponse.php' => '9df90b43bc1e5980411b498ecfd0746200cb9cc5efb25a159f84ffc80a1c3678',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\ApiPayloadEncodingNormalizer.php' => '2d4ff27d8af342a6cfac51de73fd228ab28d58d67bb07fd594be90cd67ab210a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\AuditEvent.php' => '2f21471ebf4b0550265bea570dc4a1ce41cedeadf6b77530b819d7d9092ecffc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\AuditTrail\\AuditTrailActorResolver.php' => 'd8e3b42d04ecdac6d01a8ccfdf4217bbf7475bc3d22272c737c884a42afdfbe7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\AuditTrail\\AuditTrailRecorder.php' => '088ca21e7c8ea12c5a243ab7d6a5af2dff0a313d28f06d80c5f1a31802332846',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\AuditTrail\\LegacyAuditPayloadFactory.php' => '7d83ff38a430d4d8fce606b00e78a3f7431cff03d6dc3d9e1e126e4d377574f7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Auth\\RequestActorContext.php' => '5c39921c7934180c5af88285eb17661ec4db069ce21693eca16e370e1a10663a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Auth\\StaffActorGuard.php' => 'd83bddc780cfbe09312886f82b256232e879c63d97a083372628853c9ed1b3f3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\AvailabilityCacheVersion.php' => '0948dff6fc733b330bd75b4a4079817655b1253a9f1775982ac462b028870c6c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\DatabaseWriteConflictMapper.php' => 'da3e6cfc8e66e18ac0f42dde2b16673236a438b6d1fea03701e7b8864c8df811',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Listing\\InteractsWithListingQuery.php' => 'f5c94bc41e489570fb6797acfe6f99467bc6907a98c6abcce36f3ea184c31589',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Listing\\ListingMetaFactory.php' => '70cff617c6e5db4645ce0ec5bc2c3700825866e6288fb70bea13bf72b9797cff',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Listing\\SafeLike.php' => '834fed79fce35159ac87e4ba852dce31fb0850fd1c3832409691ac2c9e38d6aa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Persistence\\HasIsDeletedFlag.php' => 'a31ca84d7bf574eab72788d4529417194808d82678bfe79c86113af65c8647a8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Persistence\\HasRowVersion.php' => '48da7ce90aa2d2ecfd197f2b5adf1c312c1472cbc4d14a525d6649e14fcd27e1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\Persistence\\UsesUuidPrimaryKey.php' => 'b6ec0c6d327b2e46c7926bd952e74cfb3add7df5c7effed5fc43e6cccc3fd94e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Support\\ValidationExceptionFactory.php' => 'a24c3a76838f4adf873a0d9842b3adefc2291a200691d3aa7e7f8259bdd476bc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminBenefitsAdminFoundationHttpFlowTest.php' => '312e7c10271b73c756c435567c84f19d1af82388b01b09df04874fdbd9b17fd4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminFinanceTaxInvoiceFoundationHttpFlowTest.php' => '8c1c4a592e36ba4b5d73f559571702d965c79c6e7e07ceecc40418ac17b96a80',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminImportIdempotencyTest.php' => 'e956cfb1b93c78771bb65d409e81cf9fad8f7d88fb0ca7eada5ac2d2abde3dc6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminInventoryFoundationHttpFlowTest.php' => '71d158c85ee9b6a1de754fe08b303bbe99b2b16cfa5904f7bc11353534b9a64e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminInventoryKitchenPurchasingIdempotencyPolicyTest.php' => '82520d1cdab7a134fed724db5535f224858f847c27382d6b0bce6558bb744d6d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminInventoryKitchenPurchasingRouteSurfaceTest.php' => '0470a2b89c43aea33407481913a10df646e1764c15c7846d2e7fb427067c7d8f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminKitchenRoutingFoundationHttpFlowTest.php' => 'f5249f33d6c6a9159dc0c4eb29895b868696827c8e5b2c31a683ea6399f830f5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMasterDataBulkImportExportHttpFlowTest.php' => '8eb1a7f47fac6c68cfadfa1f73a4d69ee86f2516aaa48e1a9c1494e3ce2126bd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMasterDataIdempotencyPolicyTest.php' => 'abea9cf90f90c26693bd236ffd041270abe5b0fff298285732d270637f85efc4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMasterDataRouteSurfaceTest.php' => '264bfd48e2f6d11bdde9814b244e3a2a5ea0ba8c25437ea55c8bed5cac1a2aca',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMasterDataValidationEnvelopeAndPriceWindowTest.php' => '200cf5d06225bd2e560da9d043987a1b2f4908f5fedb18ab4dba33ef54a43d06',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMenuManagementHttpFlowTest.php' => '2bdc0fc8f073f82184f657af3edc7adeef81bf9fec9e6655dececfed18a76e36',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMultiBranchDomainDefaultsHttpFlowTest.php' => '24eafceccaf13ba62187820ed0e1d5f0a189e195e02699c954d29562125e68c7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminMultiBranchFoundationHttpFlowTest.php' => '2aea5c18a28c27d4a7ea33343b6c54a6d8e8fc48199487586575fb8cf127848d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminPurchasingFoundationHttpFlowTest.php' => '0aee8c40cef94a9fb8511dba4e1062b3d133195a19bf8a32cd3b5eb6e0769a61',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminReportingAndMultiBranchRouteSurfaceTest.php' => 'c250491fd93bdc5312cfd2dcfab8b215f1cbac5297c8d5be59e874836422f3d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminReportingReadModelsFoundationHttpFlowTest.php' => 'eb88db10b061a97f1d66fcf7b97923194f6ab14a68864a8ddc92a370284b45bf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminRestaurantMasterDataHttpFlowTest.php' => 'b82cd9141502ec6450f99224c46fa3c74ffa08d37d30ac640e088b19e716ab90',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Admin\\AdminRestaurantMasterDataRouteSurfaceTest.php' => '5980202e2e4ab4458e9687d5b065e2a74b0d738875597b0a0c42e6b89a9cef7d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Auth\\CustomerAccessSessionFoundationTest.php' => '197bf33d1b4b35fa794bb44a7758428cc5f1eed4dffd4987b6464cf0223db0c4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Auth\\CustomerProductAuthHttpFlowTest.php' => '0236b9342a353f6ad301e20c74b114d88dae6cd47e6e5eb5856b8843a09f371e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Auth\\LegacyApiUserContractTest.php' => '8bfdbb30d790996b2a862b7b8d7088629500a1bd7f840cc7748010972a4e4c4e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Auth\\StaffProductAuthHttpFlowTest.php' => 'bbfa4169ad93e23263e6c009509e981823faf2cc8943097e22a37188074c3357',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Auth\\WebAuthErrorEnvelopeContractTest.php' => '6683959248895c9d75707c369242672031d9aa5568becfb2419c7da3d6fd0a62',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\ApiConsumerArtifactsGenerateCommandTest.php' => '8f6efb999084306dfa2d8912883703c0548a73e98c3c7dcfe306de0325516a4e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BackfillConfirmedHoldLinkageCommandTest.php' => '47577f334181cb2927f4856ee91a51d333ff0dd26e398fcd5ec8cc9b4daccad4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BackfillTableStateAuditContextCommandTest.php' => '7f7f650d330ce1ade039c52705fa42126e381c2cc396484448f2ef47a57c76e1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingAlertCheckCommandTest.php' => '0ab6cda8134b48bbb1ae42eaf6ddff4d9abf378035f997fd46bbe88ed54eb4b3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingArtifactsNormalizeCommandTest.php' => '88dc316ef3d28b9d12bd1891f7efb501d6b3d1c9c0ff144800e5933373207805',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingCoreOpsGateCommandTest.php' => 'e23936874adcb713e1a8d13f03edaa431db2a21ebf42b7415f464dc73b4ee7cd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingDeployCheckCommandTest.php' => 'd2faedbb09e67fa948e93f441e3aed5ace8252a3a4df88314660e398a499d466',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingDisasterRecoveryDrillCommandTest.php' => '10804a8e366096074e0195671710797047b6714d38a7e85f28d36fd6cff8a2cc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingDoctorCommandTest.php' => 'ae1068158c45d813d59d7871807b5e00d5264d85b24e2c1e746827cb8981eab9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingHarnessEnumStateCommandTest.php' => 'f178af96a3ce695a3edd4a2eb814fab0f1649ee1704467b3b51dd295ef1f6965',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingHarnessFeContractCommandTest.php' => '75471f4d01c0e40c06510c29224484c4f8b385dc967b891ebd9a4db673ba9bea',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingHarnessGoldenFlowsCommandTest.php' => '0a51e002d95ae190b3fb50d9cea22d480e49bb8e9d53b963d910ca40661da278',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingHarnessReleaseReadinessCommandTest.php' => '27b07f991ad5deb1357bb0bbb30579448a7f00bfe960e8167cd0e5041e9bd66d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingHarnessWebAuthCommandTest.php' => '6156cbede051fe4e843bdd354f50e41b354462c48cbe2891579a8730fcac7c44',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingLaunchReadinessCommandTest.php' => 'db6613a8faa66711206799d5f1e72c2128d101daa36f3af3a1d1b247c20b3cf1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingManualEvidenceInitCommandTest.php' => '37dcc4f38e5e9fcc399a00bfb2f0565c018c29213109bd9f8e5a5fb622049539',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingOpsHeartbeatTouchCommandTest.php' => 'd5633a4fb14552337ff184d5bfc9328d5f99dbb0feef4079f0e8b0c9199a33d6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingPackageReleaseCommandTest.php' => 'f6cb1ed9cba439384286d80ed966f6944bee59465329ea969d6a8ead0d3194d8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingPerformanceVerifyCommandTest.php' => '9c7aafbcc876e3dcee21ae4337e8049202e0d6043b62d288900f04f1b3d2f9ab',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingReleaseBuildCommandTest.php' => '8382a6aa73a02e21bc936326a17cee907762d56d588239d489c0003e59e2df8d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingReleaseLoopCommandTest.php' => '9c28cbe22f67f892f39edc5005c98c5a9a22fe19dcbdf11d5bb7bc8e7a61a158',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingReleaseManifestCommandTest.php' => 'a10c540e5fe8fed4990899376b9a7b8fc9564a9e1f3e6fdf4f5ffc4dbf09ed38',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingRoundFiveGateCommandTest.php' => 'c35eefbea6f0ea3273692ed4bd70201645918f09701dd8bbfc566e73231ef7c9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingRouteContractReconcileCommandTest.php' => '1bfe8a04de21a1121f9cd8ac75dbf6ee6d697830f4493d18680385a3ad8db7ea',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingRouteGateCommandTest.php' => '48de960ad6e8f3114551cd562211043457871b061a7d5e2c1a9e64d610a73b49',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\BookingVerifySelectCommandTest.php' => 'e9285279b16717e59283b7d3eca903502e260ccde9cd14b417e82451978c0733',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\CustomerAccessSessionBootstrapCommandsTest.php' => 'b7a0644790e60b2c8e1f80dee3a125e2abff61bd74994b0c63e109964b87e11c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\FeatureFlagConsoleCommandTest.php' => '0f88ddbebba199032684e3231113aa54c4dd6be483495eb88bc05090276e9f8f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\NotificationOpsCommandsTest.php' => '20581915b0b1d1a5e104e16226241aad0fc63c279494e01511200548197b6f7f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\ReportingSnapshotCommandsTest.php' => '11d771200cd7799cb5ab36908fc1ffed029d70ccab731eb83892cd0b9f42335e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\SiteBootstrapCommandTest.php' => 'aa535c16419e76200b7621cce54f42c29baa2bd93d10d5dd04edd1881fb7200f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\StaffApiKeyBootstrapCommandsTest.php' => '265174ab9482b8af3afeb084e504364e5b04703bab0549a9b89b31e2936565dd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Console\\UatScenarioPackConsoleCommandTest.php' => '5ae7e6c7a06c06a9411c022751dbed926dea98c98fb0cd3bc7d8fc7b9d597ba6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\CorsContractTest.php' => 'bb306e837b41e0f0f0232f07b6c28e99288914f37a1a4d5725e78e819c0f0d38',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerBenefitsSelfServiceHttpFlowTest.php' => '7197251fba2a780b098bff8f05b9829015e9f620a3700f20dcb18e584681c47a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerReservationBenefitsIdempotencyEnforcementTest.php' => '22ba9c737930ed1123ba05722945007138a2c9ff671d2610dc3e420fcb5f4260',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerReservationBenefitsMutationHttpFlowTest.php' => 'b6c51aa5f1916f120c0a08e920f343be54b284cfb8832c3c16629fd847fe7f58',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerReservationOrderBillControllerTest.php' => '30f9d9e9635e0b8186ea59cd4d01978d37577d25c64d7f1fd0de19a6bdde4717',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerRestaurantProfileHttpFlowTest.php' => '2e8114bec184fa27cafe90234a606eb9a061a3552018053c0d8d046cdee53368',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerSelfServiceBolaReservationAccessTest.php' => 'e3466e870b144296063e929a121ba87cb13f4714848b252d3a2ec55c1b24ab94',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Customer\\CustomerSelfServiceErrorEnvelopeContractTest.php' => 'cd5290f7a3640a4a80759eda6957a637e8a2fe2589c0791e4a8e62e3844b2cb0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\DataLifecycle\\CustomerDataLifecycleHttpFlowTest.php' => '33180755bca8f50a9f07860c788909fc46c04b38eafe5dc9430c8e5266146cf7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\DataLifecycle\\DataLifecycleRetentionConsoleTest.php' => '93651a7fb7ba87620b03e3a64ac5a1d1cb5dcbdc9ff8d30478c9902967024227',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\DataLifecycle\\DataLifecycleRouteSurfaceTest.php' => '9d93ccbf40c6ef988c339a2344a0786a86e8acc20739c9b907406d87c258da0c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Financial\\ReservationPaymentIntegrityFlowTest.php' => '0064e58be5ff00f23777bd6c2268312a2d083e27e55bc380316a8af7d8ef235d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\ApiDependencyOutageEnvelopeTest.php' => '43ff3b5bf8891d1897806d113cc2eb22bdce5009b1967e48a790ddd9ad02a502',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\ApiJsonResponseEncodingMiddlewareTest.php' => 'ee3f554a2b91f366810ee511b4e9352ef41761eb2b28957dfe9dea8020ae717b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\ApiListingQueryStandardTest.php' => '5510176a9732b1b01412da0701ba0eab80035b0781ad8d807654d7d3dcb13356',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\ApiValidationPayloadCompatibilityTest.php' => '6d604f6780f57a1e7d279a10afe2cc74f0e6fee932e7b411a31d15ab1f9fcae2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\IdempotencyMiddlewareFeatureTest.php' => 'a1744d35bfa1400e7a896223dc06fe18f791add138ea0949081004a748b586c1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Http\\ReservationStatusIdempotencyScopeTest.php' => '6f18af75fe6a715950e09dcaba9f029fe1423d64957cb90668fce7c9f741c92b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\ApiLiveRuntimeRegressionGateTest.php' => '5f4b4327027fb253dc51f47b1d908ac70ccbc9389836e87cc63d938ca0f6388d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\ApiOpenApiArtifactSnapshotTest.php' => 'c399e6ed09e82ab47dbe5fee61751ea44b65a51af261508d9ab5e7c123066685',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\ApiOpenApiContractCoverageTest.php' => 'c59f4a27e24c15d10afc5e424784b4a5ff7625998598c15f1f1187b525384c71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\ApiRouteSurfaceIntegrityTest.php' => '1694fb3aeb04936dbe3370c5f05b3ab60d049735fd678cde25bc680c4d53e701',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\ApiRuntimeSmokeGateTest.php' => 'a1ee9e11ba3e8c12e141f07f346b9ee512fa89ff458ad54b9ed6bfd914fbccc7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\HealthDetailedOpsCoverageTest.php' => 'ef74c75823a5d11a9b70771fb95bbe10990c7b8502e664de51440fe3ba014c71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\HealthPublicCoverageTest.php' => '8e27c4c833f48e361ca4158dc4e1321ce08c7c0bc5a648bded3f885f346d7054',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\MetricsAuthorizationCoverageTest.php' => '84b4269efc71020eabfe92f78ca1e5177a1485276a16f01ef0731defcec111a7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\OpsReleaseAuthorizationMatrixTest.php' => '5d1f7aaf0143cc79f50969a4fe2cc71a60ea849c8aa96bcef23c659c2e5a5f34',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\SecondaryDomainRouteControllerOwnershipTest.php' => 'e386dd2ee8c6d72a596200261d57d25742388dce75251906885504d825eae87d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Infrastructure\\StaffLegacyRouteAliasContractTest.php' => '53fa3939049ce8324d69c7953ce1fa237ebcd4e7442226ddfbcbc9d0bd26fa05',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Loyalty\\LoyaltyPointsServiceGuardTest.php' => 'f99d2f22b452fe4a93ca4e998bf46352e8e4720e42ae00889285c0f4b02eaf8c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Loyalty\\LoyaltyRedemptionLifecycleTest.php' => '581a52dd8a558089e66c2b45b4150c8afe91175e3a64d61d349867cf658ae1e0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Menu\\CustomerMenuCatalogHttpFlowTest.php' => '88bc50f20e95af8e8183ed2b00c1c98caef09e6b4ea93678d92d36cded5e83b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Menu\\CustomerMenuCatalogRouteSurfaceTest.php' => 'b54245aecbe9c9751f835af684d98ea07f50600dda2a0bccd1b52bc20b0f4629',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Notifications\\NotificationOutboxServiceSmokeTest.php' => '96eaf693dbd0387bc01c4e04988ad4303943e2cad1a6fb6e83e865c29840cc0e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Payments\\PaymentProviderWebhookFlowTest.php' => '944d14b197751ca3eeb81408fed77f77ff5894c81f14dadb9d835575753ccbb4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Payments\\PaymentProviderWebhookRouteSurfaceTest.php' => '1c92443a7b82c6cb9c055d3084485816e0b7623993ad59c29b492930a3c71f3f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Performance\\HotPathPerformanceBudgetTest.php' => '1bc7e3353a03452b96a508ece58223151198702860bd764f4234ddaaafc14110',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationDepositPaymentRouteSurfaceTest.php' => '1363a9eecb6d46356b7b510efce1b5f454a38e46bbc26f6005a530c33186ae5b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationDepositPaymentSessionFlowTest.php' => 'b353a81e35a7959eedcce6c63ce9d8aab5b623d38633b26460700352c66de18a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationDepositPaymentVisibilityFlowTest.php' => '5eb52b87037d6668a7f478d8ef3f19cec6e04341210f33efd8a3c3226d154cad',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationDepositSelfServiceFlowTest.php' => '4cafd7fb6eb4fe73f372bc43b55b6e29bb177fe71209b09cf0825d15d6eb0fbb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationDepositVisibilityFlowTest.php' => '810ef4c30a33112076c97d7b5c360658f3411f37b3a373ccba0b640ed438a5ab',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationOrderBillPaymentIdempotencyEnforcementTest.php' => '0edffdea5fe6fb264310fd75fe705cc8f6d55808bf131a1b3269f18595ea3dbc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationOrderBillPaymentRouteSurfaceTest.php' => '81b8b34809baeacc5f05083c48494307b86d4c48da0a318d6214a82118142d07',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationOrderBillSelfPaymentFlowTest.php' => 'f3bafe6337a9920f40ca342c6bfce448bd98ff80cd507bdff667936f4efb3086',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationOrderBillSessionAccessFlowTest.php' => '921e7df0f420264cbdfc70df9f59d5060ce995b1d01064e71412b855d4a8a0cf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationPreorderManagementFlowTest.php' => 'e653f1cf68c663c2dd7b1085c30e846b09639c1f40071ac62907154567852ed9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationPreorderRouteSurfaceTest.php' => 'c250afef58cf3d231154c5c299d10705ef539321606ac4ce89eeed45c5f39595',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationPreorderSessionAccessTest.php' => '21304ebcb08150139f4b5d4bbe00b107dabae60ca592ce09e9c384a2425dbf0c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationSelfServiceHttpFlowTest.php' => '99b8e8c7e3877e607d301a4ae72d6900430fa16cf90ba6c7a71dc64daefca5c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\CustomerReservationSelfServiceVisibilityAndGuardTest.php' => 'd47bdc484b70d8ac39c72559e23d140bd428b5bd23686a782922237f89e148cf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\ReservationHttpFlowTest.php' => '8d2b88413cb63ba1a9eb5ea446fbec20afd2cc25c476ee86fe21a7fd5f4ed28e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\ReservationServicePreorderPricingGuardTest.php' => 'f4533d2cfd576f58ac09fc70dd09bf7e5b9244b1c010d8c5fca0fc73cd9417c8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\ReservationStatusHttpFlowTest.php' => '0b0dad8bf66fc729749c84f4e450cda7dbe04551c588bb304594e66374c3f7e8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Reservation\\SharedReservationDetailBranchIsolationTest.php' => '78e3ee3192eaebe423773ddf95107a52c37b844d570423d801156412ce9198ab',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Runtime\\RuntimeMysqlRedisSmokeTest.php' => 'e002a1096e6bc98cc0619130f9cae64c3ed4bdb42bd6ca0e4da1abfc0c61b446',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Security\\SecurityAuthRbacBranchIsolationLadderTest.php' => '393f1f92b1488d5bcd8e2e7f7295463761b27411d8ed7ff2d294cd8db551039d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Security\\StaffApiKeyProductionGuardTest.php' => 'b40a0dd5a6ee6e7cf0807f92d0141aa16043d563aee6399590f3c7f56ff1f48a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\NotificationOutboxServiceRetryTest.php' => '370364921592ea7578499a7745c0904a31d6580a0b4840b8fd60ffc3bef57b50',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\ReservationCancellationServiceTest.php' => '53d839f73eafec2445364692766bd0adc2a567d5845020da061944dca86cda78',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\ReservationFinancialSyncServiceFeatureTest.php' => 'b054f2c2cc3e3703731093255aa55423615e7bd85ab7a36b642120b04fb23bb7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\ReservationFinancialSyncServiceRefundTouchFeatureTest.php' => 'acf86f33aec2f0f4a3f00501c1935fc6ff4d2a3a069b7ab0fcfde2be53486b90',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\ReservationFinancialSyncServiceTouchMutationFeatureTest.php' => 'e931ee0bfb3a0a6ab732a549bd7ff947974dca281870e1264a18133dd9063db1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\ReservationSessionAccessWorkflowTest.php' => '52f886293d090f5969e0ef2b2715acaa9434506c22f614b054524b1b836815aa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\RestaurantTableStateServiceRowVersionTest.php' => '945ae2cbbdf86380a602a2eb049f8eb06185d28a78a6ee253608913c3b5ae447',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Services\\TableHoldServiceRowVersionTest.php' => '3250e96ebc2922ec5682266972746680fe1c74795199a2589cf8c4ae35fa1be0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\OrderItemInventoryConsumptionFlowTest.php' => '22ac9b2d95cc23e1503eb02c83572cd531b18fb99ef965e06e7cd1822eae4ef0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\PaymentCaptureGuardRegressionTest.php' => '37f0b3d38e9a38cd1167ebb4d47a10bdb459d19bd6d1fa8bc5a9ee5a9e106df3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffAuditTrailHttpFlowTest.php' => 'a2d1602ad508f12fd816f27c593d732edbc9690afed24c1b8521ce673e5831b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffBranchAssignmentContextTest.php' => '2adfdf834fbfe8d79d0a441b67207671b01d988bf3fe57612d849f0d1e9f2c70',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffBranchScopeSecurityHardeningTest.php' => '7f513f3b3261b91fd11abbfb9515c3108a30d02bf60c2842210fb88bfb7918ff',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCapabilityHttpGuardTest.php' => 'f31ecd21c93db7918bf8244a0fa3f0369ac004b254c664e146fad8ef2beec114',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCashierShiftHttpFlowTest.php' => 'd4ecc64dca59fb7ccea3e54831266874d53f664e1396a92e4e4facfc0b178865',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckInFlowTest.php' => '029ef5d2a242c44307ec680964f377bc728fcecc633d9acfd9779b0d217e1a71',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutConcurrencyGuardFlowTest.php' => '605b5a1b46fadc772681423bd3de7741e9ba8e34137209c0b2050e54d64e3eee',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutCrossPathReleaseGuardTest.php' => '318cc7a4488866a13165fe654bef5c253ca122e57a037370337446d28ce6d6f9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutFinancialIntegrationMatrixTest.php' => '9aed9cc886e72854673a1a7056cb7b08fcae8eea001103412935b0c2f903b0b1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutFinancialOutboxAndCoverageTest.php' => '4fbbd5cabd136bb235998dbf24398f0e78d02da6be48e55b620261994087e0f8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutHttpFlowTest.php' => '3780536eb07bbaa27cfc0c73ef42960092856552ce39b3a12c6b754bdb601fb9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutHttpGuardFlowTest.php' => '8d70c44c5b9fe30b6df8249e56a9a46b935f9e040883f396799ee50060b7b8f0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutIdempotencyReplayServiceTest.php' => '7dab3fa2292f1984dd4c2ecca1d63ca3c3335d93bad83734cbb2b8619111d523',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutRefundAllocationGuardTest.php' => '203e1683e45e1804672f4379e528616b9fe80b43aff22cef45e12e62358e8c69',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutRefundAndCancelServiceTest.php' => '75b000053e4b7dadfc9eed90e4f2c143c2112204c30978f1bb9b2e700a77ff80',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCheckoutRefundLifecycleTest.php' => 'dfd0b1e3b28dd242b660883dcd13f3b3c48a443d9ad87bcc855127188cd9b8be',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffConversationInboxFlowTest.php' => '9b1167c8b889ce29e9a1fa222dc0ad2dc132bbb4dec79506fa26ac9ce32fc6b8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCoreReadModelsHttpFlowTest.php' => '10bb3464d4852c12e05e9401841f67b3685c329a66e7d576db800a8a265df427',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffCoreReadModelsRouteSurfaceTest.php' => '2945388ad523ea09623feca6d31f125475ae9124d59bb78ef2d5beeb67acee1e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffDepositSelfServiceOperationalReadFlowTest.php' => '345369282f6e65efbbe172747a516131ce3fee3082d302d05fdf10dbfc5c5bef',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php' => 'e176309c08de7318caea81d2ffa5b79eaa02e805bc0f09cb4365a450ffd8a78a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffFinanceReportingIdempotencyPolicyTest.php' => 'a1bf2259b8f23191b9bfe14992e382e82171749fd4f28f65dd656fa1fc20595f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffFinanceReportingRouteSurfaceTest.php' => '0860da3e00165984e0105c70422b73e7952563a3f7fb6dee31ec8679a141cdd2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffFinancialReconciliationHttpFlowTest.php' => '46529396a4ddb91781b92eac4caedfb0e6fb35fbb13b057194f12a9fbc9d9ff9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffFrontOfHouseOperationalClosureFlowTest.php' => 'a04b92099a7281a5e14df97d8f0e6f6bc1c331ca1c878453fa9e1bc118466f55',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffKitchenDispatchFoundationFlowTest.php' => 'ece9db600ec95355dde3265a86845235f69065afe7cb345dfb9ce60c23d92128',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffLegacyAliasHeaderContractTest.php' => '18436545511b33c1ad2f98e0b37612ca5aa6f1e4e88c67ccecdc23f3f504e5b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffMoneyActorBoundaryServiceTest.php' => '9f1d834f11951760b14880f1e7db7c3b5194d16d99a858a8653d34afb90c80e4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffMoveTableFlowTest.php' => '79045225045b69aa241dad51f37adf4df4643e13fcd37091aab903704732fccc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffMutationAliasReplayHttpFlowTest.php' => 'b78a8c2d47a58aafefbc3066e8dee14b5836a29a1ff48c6caeaf91ca0f99dd43',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOperationalRealtimeFlowTest.php' => '7a130177081e1d6a25a065dc45114837cfa316ac7d9ed241d583d436a43d091c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderAuditTest.php' => '3a5a6fe833daabfb2f4b6c350b492ebe13bdc0262f18c73a2ea9da0eddb210b6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderCriticalFlowHttpTest.php' => '4a79a8fb19d89b3c72c7c85fc76c3912a2c5e7282b4aab28205d9f2324a593bf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderItemLifecycleFlowTest.php' => 'dc7b7d272bf03cf09ed12a4944a9bfae680ecb3f1bac8b0dad256b83f2aa9be8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderKdsFohActorBoundaryServiceTest.php' => 'fad1bdea6cc52ce1cf2f35ed2b7e822ecaa76da5b13d640fb19425da8b5cc944',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderReadFlowTest.php' => '8885c39ade21516561803f4b099dca93b47852232ff139252f70b5ac5adb3bcb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffOrderSettlementWorkflowCharacterizationTest.php' => '48a34682c26860ad02e4bf9e0d23d22e4ffca7cb77b7ef28a05cedc3625da34d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReportingReadModelsHttpFlowTest.php' => '7f5e8e181d5085005301e3cbf62cbbb5d2231a55923879900b6251193467c2f2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationBoardAdvancedFlowTest.php' => '48d654151ab7601489c1317e6f91043cc566a181674af9d90254c47b1d04e69b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationBoardAssignmentFlowTest.php' => 'fd6f510a5f8c11158c3c36716671feaa5f8701c67e4d65bb8df95ce02cc83a00',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationBoardOperationalOrchestrationFlowTest.php' => '1b93ceaa6050d53be6ddc83cf9bd87341e474515216f8bd69607073b24a0e01b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationDepositFlowTest.php' => 'fe7d063774e98af7068b6bd675c2208cace86f7a8188ba8632486c3f63fa57a2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationInboxFlowTest.php' => '6670323c7aecdc462829f51e91a49c2446622baf5f13d7cfd19e6537cf0508ca',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationRescheduleFlowTest.php' => '8ef7aa17edfbb68f818aa1dc0ea862dcd829e60c736f821d32fe55992ccb16e7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationTimelineFlowTest.php' => '420d2a203830392db63217d24a6ee4565ed178b41882201596652568707292c8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationTimelineWorkbenchFlowTest.php' => '46c3d8957ca6f93e7ecc1f878f071bd15b3a09003346767eb19c9f9e1f866176',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationVoucherLifecycleTest.php' => '0500cafc67d391f8d656cef297d780c251746e0095e56851c85f77ad1edb1ead',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffReservationVoucherServiceGuardTest.php' => '7a0e9f9831d00c12a6a67ebb76102205ded4576672bb0a4db0dffe95fb732564',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffServiceSessionHttpFlowTest.php' => '4273a2201b49d436bbd670e706a407d776b3b3bdba83aebf070471aa9c9df5bd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableBoardFeatureTest.php' => 'c6910c7d201a5ac438d093b29980c5f2ab97b44fd998fa4e762851776af717bf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableBoardHttpFlowTest.php' => 'e08349ed50e741c51baa0390db652720eacc44e3e2b346ec6c9ebcf9ae59b6d8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableOrderBranchScopeTest.php' => '5f1f5d1b78099fe9da83f5118e66d070403606edd992a14c848d60c4dbfdd7d8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableOrderConcurrencyGuardServiceTest.php' => '664cbfba462fd6f96285dc467ccc6b2a245c2f0f6c68b0b6d35d0447de96ce75',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableOrderIdempotencyReplayServiceTest.php' => '69ac885bb1369d4c80ba0a159387c4e25bddbed90c7af3a26afcd27d4372bf5f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableReleaseHttpFlowTest.php' => 'c7757fb0832ecbd19b8bdbf661bc8cd4b9362f82273c2289969fc3a97455a0d0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffTableReleaseServiceTest.php' => '29f24790ec4452d5ed6bd1e3f4224a490fb091fd17994a952e5eb4302e344592',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffWaitingListLifecycleTest.php' => '86f04653218a3597134d5aad7edcf98219d1c7bb9c46f2dfcaa328c1a0868704',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Staff\\StaffWaitingListSemiAutomationFlowTest.php' => 'a110152722de82e2102b6d58d53374a39944033f3ce25e8d9ad342c7fbae2977',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Support\\BuildsBookingScenarioSchemaIdempotencyTest.php' => '545f5176935549711a9c9155615d501155a600c9240391333df99b06fb2764cd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Support\\VoucherUsageGuardTest.php' => '245f6be3b46f9ca11bec3fc01bb5086db2b4ad62d07f94e44f8f79bf1513bb77',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Table\\TableAvailabilityFeatureTest.php' => '5d738ba22860895891571fc5e72eb7123f675517373862ffbe1c9d4758a7a43b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\Table\\TableHoldHttpFlowTest.php' => '1310223959abaf236ee05a4e683a80c7440ea376d38a6311297b8cfab11a1501',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Support\\AssertsAuditTrail.php' => '869cf2914713bdf0651b1ffd3db5e2128987852ec40ed9c24e26ba82b5283d7f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Support\\BuildsBookingScenario.php' => 'ee1398a6f4d513d3ff9fa90566a814806bf088e0453665864632b091bc132761',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Support\\InteractsWithCheckoutOutbox.php' => '8ecd29d1507700d928e85e60dcf2e3256d06e9e0719789a4664e7da5a9eb4d98',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Support\\ProfilesDatabaseQueries.php' => 'cd90953b33caef9bd152968e285dc39d1c37bb43e6ec79ee44295b16643972a8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\TestCase.php' => '606c5747ee61c86bc28fa1ac79d39d9d401171396ba068e0df4ae365fb92b2c3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\ApiArtifactsConfigContractTest.php' => '680ee1de9ab42883743c898c455a2b8f6d465686bc3b42f668e4ce57ac1e90e5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\AppLocaleDefaultsConfigContractTest.php' => '4280175a27fa1aa2c81842895c89ecf062b75ae4a0e1a2381f2d44d7007e7fe7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingCustomerBenefitsIdempotencyScopeContractTest.php' => 'a8a87aa83c3812887cd4837d406de8429674b8dc420126c4e95b73544d6fe6c0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingCustomerPreorderIdempotencyScopeContractTest.php' => '77c43e3400c3f84d91b725f529d2d03d8405edb01198d768c0774c5c4d6fff03',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingIdempotencyScopeContractTest.php' => 'a7a6052a891c8cb5d6207b9bfa5c0e294ef72b6fb6709dc43b5c4be7a250f970',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingOpsArtifactConfigContractTest.php' => '93d0a1ce47bdc067a250c15e33ddf1146fb9870ddb8fbb06babbc8e337f0d6b5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingPaymentProviderConfigContractTest.php' => 'f0e23468208813c545d1eb2abe8780f5d1deeb62996733ad4f7c250f8acf3758',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingReleaseRequiredSqlPatchesTest.php' => '29e5db6103d10a020a3cfdf018374ad6a788166a923b9e1832d09e61d76dd554',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingReportingAndMultiBranchConfigContractTest.php' => 'c5fc97fcc68e165f84abe578171db10defe4a582e6aa845bba4332cafe6682d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingRuntimeConfigCoverageContractTest.php' => 'cd8982b8ea77de2cb79ca3102189e5677a3360a535c06c79a03fb8ae5aa1adc2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingStaffBoardAssignmentIdempotencyScopeContractTest.php' => '193585d27aaa53db8df16587080373327ef589ea5921ab601139bc8c9c79a4c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\BookingStaffWaitingListIdempotencyScopeContractTest.php' => '8c9b9ddc8042343830eccadddf94e29826186b1157d01c28cac00ecd6c3f15c6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\CustomerAuthConfigContractTest.php' => '62a09fda2d76ff8fe2dc9fc5586a2b8c28396354b1af05fb84b8e127b5d5f116',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\FeatureFlagConfigContractTest.php' => 'f397731a9bf709b06aaa72b606b44ee6a0e9b725900282e8f4178f47c4efc143',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\StaffAuthConfigContractTest.php' => '9e0c39b00e7929642bf94a1f2af3a96373503b12ae362a9bfc5e2c3c0ea62b70',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\StaffAuthSessionTtlContractTest.php' => '2fbb652a01a2f7832bd4a985da42a7ac15d86ac6426a94f494596008e70265ba',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\StaffCapabilitiesConfigContractTest.php' => '51449fa57564eb5828107dea2f9188d8ca912f13193cb7a3f8859771a657ecea',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Config\\StaffCapabilityRouteInventoryContractTest.php' => '25bc56d3bf75922ca9b58f1fc4151b327c549a9891f35a30f060d03f448b692e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Domain\\Policies\\KitchenTicketTransitionPolicyTest.php' => 'e2e204ca4cadb6ff5d6fa91ac4ecb461d3534df42137c06efb16a740b0a6e91e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Domain\\Policies\\PaymentStatusTransitionPolicyTest.php' => '03aa444939180a03d1a1420dc1f2564a4abb101938ce4f9d21ca18c603801ee5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Domain\\Policies\\ReservationOrderItemStatusTransitionPolicyTest.php' => 'b16b91cf6534fb398a4b04b07325fe2ef620290e38cfb3405eddd5f6511a7de3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Domain\\Policies\\ReservationStatusTransitionPolicyTest.php' => 'cbdb2ea1d4b4b082a24e6d2276cd1ec1ed50625ad7033d78cee20bcdcb7f1d1f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Enums\\ReservationStatusSemanticsTest.php' => '486a66807e0be245e842d38a52fba9456dc338e315da7d29905cc361182b7312',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Controllers\\CustomerReservationPreorderLegacyRouteDeprecationHeadersTest.php' => 'dcb5a193d9ec343d06e17c27c6a647540510761736e019fff6246fca14ba1cb0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Controllers\\StaffLegacyRouteDeprecationHeadersTest.php' => '6b0431180704cc015e0547906d34399b2419f41004633a142afa1c95f3b906e6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\CustomerOrStaffMiddlewareSessionContractTest.php' => '875ce74af6cfe07b8ef45c951eb160f16e1f9a77be62c7e8edb36a3461c2f3ca',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\AuditTrailActorResolverTest.php' => '40db18f372aabaa7d3e13a6f3b28398d49edb72959e44adf642b4d2ca4868f5e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\CustomerOrStaffMiddlewareSessionFlowHotfixTest.php' => '6e15d645d2b59ff39c8b31059bed7df71f8ce9f5774a52e5d9ebf403d8ad2ec8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\CustomerOrStaffMiddlewareSessionPathTest.php' => '72bc5d7b97a2ebf6764d74f3a966ac4fdf07c496d330de351e6c664df5ffaba8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\CustomerOrStaffMiddlewareTest.php' => '3f0fe38e53d7b88d56026f5b36dff13ac95248e767bbddb23f04b4a5cf91dfe8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\IdempotencyMiddlewareLockingTest.php' => '930d091ce0d38ad0f4d63602760fdbeffd11d9a46676939d2561cf296a9a1aee',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\RequestActorContextTest.php' => 'acf1ae0776a89e21338bcb992ccccbcf49f2a6c258dd882e3ace89276aa0a193',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Middleware\\RequireStaffCapabilityTest.php' => '7d67dd1a7777e5faa1163a3a8b900164970e79e4182820ba5962d0d103956997',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Requests\\FinalizeSettlementRequestContractTest.php' => '5753babba3d5e0bdebd80e3865e8cbaf87aa92c2e62931ff0278fe10480310af',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Requests\\MenuCompatibilityRequestContractTest.php' => '034e796d0fe2cb9d7fe57618f599a4f7e5962bdabf6278973e0bf9eba1e6d52b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\ReservationStatusIdempotencyContractTest.php' => '40ac9123c569ac764db33b3023c5aa55e42a48a3c5d83479a963fdf8c44f9f8b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Resources\\ReservationResourceDepositSummaryScopeTest.php' => '58ff1bdf82990c643deed94f81f6e08d414190cd0603c4dbe2a20011a57ed80f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Http\\Resources\\ReservationResourceScopeTest.php' => 'd4ab33a82629a3164bdd414699eb12dcf9ad058257f427624d93e0c3a6461615',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\ApiArtifactReleaseManifestSyncTest.php' => '6299512a0f5908b4405cf7ea5a51024df34ae6c7ab669e1a22f883a1bac19ed0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\ApiMutationContractRunbookTest.php' => '91a533da4dd6f29ae2093471fc9daca00ec1bd976e55201c243fad6de9876882',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\DatabaseReleaseContractArtifactSyncTest.php' => '46bfe125be018a76e3f32a30589b2b31d9813c87427dc1ec2a4ba679627eb24f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\GoLiveCandidateGateContractTest.php' => '4ac260d28053ef06368fefcb78829bb839b4b76ed41f666a751236890fe0a7e7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\LegacyPathCleanupEvidenceTest.php' => 'bca2dd828a134d3a5f549999f7ad86cc0951f19f50489050e5b0e348dd82ea48',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\LocalRuntimeScriptContractTest.php' => 'd9f65c618900859587bc9f04cb3748b5ddffbc43ebdd489c6aea0e875c391d08',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Infrastructure\\UatScriptContractTest.php' => '7cb58572082b2002ca30875f72732eac5f2d8d33ce488ddd291af81a22bd0106',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Models\\HasRowVersionTest.php' => 'a4b69b25cce37c0839754b2767d735cd0157aa0ba525e9803f805df4023d0bc7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Resources\\ReservationOrderResourceContractTest.php' => 'f4b81c0214e6a16bc9e74581da78b6166eabb9d1df17e71cd25a153a65f7438d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Resources\\ReservationResourceVisibilityTest.php' => '39fd86315f85e75ea60c6f1cb5048f9b6b74edd7a90f5c6451c2ab4ad68d65da',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\AI\\ConversationAiAssistBuilderTest.php' => 'cc41826a2f995d7ad9cd283fea66f04eed191f2618fa1cc67a5a089ffcf9b6fc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ApiContract\\FormRequestSchemaFactoryTest.php' => '9f5b33ae20423ebf07aa003781b1bed0a52c4d1dd2c60607c5a8d2ac7a85558b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\BookingDeploySafetyServiceTest.php' => '698efe7eada34a1fe68d7e3ffd562e6c467e8e51f6cc9b9cac14f49865662a74',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\BookingEnvironmentValidatorTest.php' => '1222dc0f3f55fa22370996d21b000934b4d61f8b5ccc22f8c00bea458b038b8a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\BookingReleaseConfigContractTest.php' => '7f47684d7d20a79f224604b67a12303483eb3ec6a442bc7a8b26c318ca20b1a4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Branch\\BranchContextServiceTest.php' => '905f9950e063d80420f81798d29fd406126800973b7d33a88a37579a0a5c78eb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Branch\\BranchSchedulingPolicyServiceTest.php' => 'a0d45981c7b352e0e34d9c1cb12eb36290381d1170a771da23857752ad7c603b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\CoreOpsGateServiceTest.php' => '81aca17a37b264c211c13b5720fcac6d190700f1624023764df9e08d84d7f68e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\CustomerReservationOrderBillServiceTest.php' => '86419f15ba13e8e3946c6c2cda7d3585a0a9c36dbc2ec092f2f3c82f7d8b7ab4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\DatabaseContractInspectorTest.php' => 'ca6db289ac6e8a27e9d8bdc75b9ea71bae141b89cb3c1c12d403617c552751c2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\DisasterRecoveryDrillServiceTest.php' => '13c684774e778322ecadab988a877457d56ace4d413d2267b54f2ff21c491dd7',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\FeatureFlagServiceTest.php' => 'b2212b45c7f1f3a0772ab5d894e16b21f39ad4b4eedc74efba6e2e62a80aa5f2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Inventory\\InventoryStockMovementServiceTest.php' => '56b50a39b609a04354d20d99af73a4c6ec3cdbb98dec63c1cdfd5c422cbcf8d2',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\LaunchReadinessServiceTest.php' => '25cfd71beadc87444ad5498bea2913855d4b283c6c08b26a38de8c7ac1e8c23f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Loyalty\\LoyaltyBalanceServiceTest.php' => '4a67adab672946dd697fe8299146e41e68339fb24f333eb82bb1e8dc6b8aca8f',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Loyalty\\LoyaltySyncServicesTest.php' => '9efb6bbeb94a71fc9497c94dbe4b45772e88008ccd46c466c2101ef84c431901',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\NotificationOutboxHealthServiceTest.php' => 'c7e58aa12613bbab09f2828a0b53f5ffaceddd3069b7d05756b779af54be99aa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\NotificationOutboxServiceIdempotencyKeyTest.php' => 'b21159c3643e1c9bb3c65943b20e06b03d47bcedb9c5162d38bf4e064e530cba',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\OperationalAlertServiceTest.php' => 'a4aaa6b639d155abc5378eef97445bbf88cae617cc7a513a5c99a3ac2f38a3aa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\OperationalInsightsServiceTest.php' => '16bb2de109b4a89386cdf96117485a97420ebf224db570064d4702288288ed3e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\OpsGateArtifactServiceTest.php' => 'b405676f069e77088fd5be9bad1c23b3e42b225836228a3d939c72cc3be86f81',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\PaymentIntegration\\GenericHttpHmacPaymentProviderAdapterTest.php' => '16cd139ec1bfdf46894ed3bc888342479bc2195019fdf992a92e1acbb34cce87',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\PaymentIntegration\\PaymentProviderRegistryRolloutContractTest.php' => '6b34aa609bb56a16a3029e0ce28c16fd1b73e042393bbbb5e66222bc0dd5fba5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\PaymentIntegration\\PaymentSessionStatusTransitionPolicyTest.php' => 'feae5a23a329d2be4511f7a92a3cef34de7c55190930c6861588b1fd5d49eb4b',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\PerformanceVerificationServiceTest.php' => '0941628aa9ad3b0f4ae1492d820a8d211b812bd0b1b99e99275879a6dea13b1a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleaseArtifactContractTest.php' => '3faed9b4833bc73ab092f3386dbbe1be92a9d8a1d752199b36687e6af8e0e0c1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleaseArtifactManifestServiceTest.php' => '13d25999718e9636a2faffe7fd337891a2f8839566976b1b25357b368fdde227',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleaseArtifactNormalizerServiceTest.php' => '62e401aa9f7db988a225679c7df88fb44611e1b004c91ba99aea22c4b2351719',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleaseBuildServiceTest.php' => 'b8f98881d91778ff8e374512ea05eb2edcc33e191d53d9a54b10f49688654277',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleaseLoopServiceTest.php' => '09db57eea53cd86f55709893d34855eb72ce55788c86bf7707a9a44c320c6beb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleasePackageDefinitionContractTest.php' => '156a61675f19b5aa918994a7224f997f77b918f097043634b92f80432e3dd4e8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReleasePackageServiceTest.php' => 'bfc31b2b04d0c119d04c0e9a36ed64ef3e8322d43c74aa615e45313a5b0a911c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\ReservationFinancialSyncServiceHardeningTest.php' => '9f46f0aaccee2248a64003719d2e04b3348ddad335fa1217c1e50bad69793c97',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Reservation\\ReservationDepositReadServiceTest.php' => '56ada9643845e6ddcf8728097c9d089ec7bf30b310783a3e4359a90edc8ace50',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\RoundFiveGateServiceTest.php' => 'afe71add2afa1a7a01cb6e13ae2622d88d955f017b151403f62a148eaafaa914',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\RouteContractReconcilerServiceTest.php' => 'dd88b6c5538bb6c0c467f526f08221ad188cdab23525a615f9e2b91f5ad32447',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\RouteInventoryGateServiceTest.php' => 'b1ca0987fd179895e6217d282f15f5fd5bf460a58397eb20ba85889e205b1ef5',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\StaffApiKeyStoreTest.php' => '936c296fccc5fe179316a7f9f3dc14a7345945efd7cf92883f1014978ee164c1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\StaffCheckoutServiceDuplicateConstraintTest.php' => '92f28671eb5d2cf077335c77b43ae807a5d62db0085963e72bd712e55f78c496',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\StaffReservationDepositServiceDuplicateConstraintTest.php' => '0ac7d6031deb1098257cc02a9cc3e6acff790102c7cfce5f5bbfe18dda0aaff1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\BillLockServiceTest.php' => '40d1414ce294ad324ba69f4055e2e7f7902fb807aadcbc7424406ff02ff8e1cb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\CheckoutResponseFactoryTest.php' => '2006c1bb6935ef9270b67cd8723e9b8929aeb081cd82c4fb8a4beac8a172be06',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\OrderSettlementServiceTest.php' => '0320605a69c50ceb6f98ad1b01c5e2aea0ad9836f3448c0c1d9c222a137749eb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\PaymentCaptureServiceTest.php' => '7dcab62a084700c6df3af1a4411d2667af6ed841c51e34de9b626099ab3795f6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\RefundExecutionServiceTest.php' => 'fcae1ca3ac70fd437810759c7cd74e2469a8807410af5d8dc77548813f1b8911',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\RefundPlannerServiceTest.php' => 'f57ed8d764f2a43ce57c5c0ffeed389e5898a0b55931658fab4f5e452615dd91',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\SettlementAmountCalculatorTest.php' => 'dcec5c3d03763ee87db9ed321c1f31d2898d5badb471ba825ee21a4877d44b3d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\SettlementFinalizerServiceTest.php' => 'eb95e429c611f8af62773e4f032ee4ba2110e0a02c8d9a532f7323df437a8b35',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\StaffCashierShiftMoneyTest.php' => 'f88a2017edcdac1d09041b610364e742dbd58b42c84cefe66567a2e18a03d14e',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Staff\\StaffReservationInboxServiceTest.php' => '5eca106bcb173adc8e3754433deabfde4546ab33fd83c7e699f2ea39eb746efa',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\TableHoldRefreshMonotonicTest.php' => 'eb113aae55fef493bc1832f8ebbab311ff19995752ba45ce516ab7bf1974534c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\TriggerManagedRowVersionContractTest.php' => '198223833a8dcce922cccca9ce71741ed8734ea43186daf66fc6651f48177300',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\Verification\\VerificationSelectorServiceTest.php' => 'c7f56b3c953dcfad5fb9f591fc3afa81231caf2fd21ee00361afec1d0a1e4ff1',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Services\\WaitingListReleaseArtifactContractTest.php' => '4074e34447795778c604914890a1379fb9950704c292fd8e6a5c142d4d45c70c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\ApiPayloadEncodingNormalizerTest.php' => '676043acad8949f7d767a6b1a72d5a20194ca0b95f3da2aff1240681b15b5d42',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\BackupArtifactManifestTest.php' => '5cad481aebce3e0e39eb9651292d18f5248ec74f6cdc7a5d7a479c001a16ba2a',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\BackupRestoreManifestTest.php' => '32143284df79e9203b723a4f96200fd6c8337377863fb95d6dccbdcd8cce9240',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\BuildsBookingScenarioFailFastPolicyTest.php' => '862401881a0f0809ec084f952e4f273c3b6f76874db9fbf0a050455bb93c28c9',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\BuildsBookingScenarioGeneratedColumnGuardTest.php' => '49a2d99d995262a967fd12307a81e126dff0d277829c0763b03c8605b1e11620',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\BuildsBookingScenarioInvariantGuardTest.php' => '8f924314974087a6284fd0deb72ad36b767edf7729f34dc7aa2e5712d29ad456',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\DatabaseWriteConflictMapperTest.php' => '6b9d7e20c63701298b7c9547714f5c3c6205048eac7518669f2be911e53e3bdf',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\LoyaltyEarnReconciliationTest.php' => '5d6912351a0dafb0b46bdea2f68327e9493fcb3d9dc942df6f60072d6dbd26bd',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\MoneyTest.php' => 'c63413452ff7ab23b86dbfb6b491e8823e8d4b30b1b998f2040944488c68ba73',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\OperationalHealthEvaluatorTest.php' => '1652822c3cd812a6a4c79e39eaa67467925ec6daba760b93931eb030516b99f0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PaymentIntegrityGuardTest.php' => 'a12dadfe302c6916af3a8b1ea6de774eb946f452c2916a436d5f3a335127eda3',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PaymentProviderPayloadSanitizerTest.php' => '29e0c720dd82153bf6cc1c144ef2ae902e4e740afd8514f3f17b9cfea78a0483',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PaymentSessionStatusTransitionPolicyTest.php' => 'b59427a6971d3c267f62c192c4453e6c43265dd790b3d574040cacd37f1f1e60',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PaymentSummaryHardeningTest.php' => '0ac71249db9186d6613fcff519e1f3310bea05808600f3406420ec40e2f2db23',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PaymentSummaryTest.php' => '959ef3c8b46b7fd718c43bc1b4b3ffddd176de2160289e8fe35201290581f1d0',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PortableBookingSchemaParityTest.php' => '220767f427e86199606280f1071bf4cdaa7d291744703165297a3fa6e3c8474d',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\PortableSqlSanitizerTest.php' => 'ce618a864b77cc8a42f27721b5e567a046a24921505436cd412ec8ffa69eb687',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\RefundAllocationPolicyTest.php' => 'cec8c4f20b5ccbe07960b43167faec9f0521e31d0433304506a626e160493ffc',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\ReleaseArtifactBundlePresenceTest.php' => '833623193015cdec5a87fb34d94eec98079a2eb44a43c5bf46cb4ed680592c5c',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\RowVersionSourceOfTruthInventoryTest.php' => '8d06a53136339ebd82acf9809afac1e5e224576ed023be8f6f5169a0e0d89fbb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\SensitiveAggregateDirectWriteContractTest.php' => 'e53ee6846a2232eedd1d197966e718ec5a5dc671b2c0e3b88a3746dcf60182ff',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\StaffApiKeyActorResolverTest.php' => '1ca49da52206babe108bf3870eda1f5a3300ce4173a8a2c9f89a64bc4db89c60',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\StaffCapabilityResolverTest.php' => 'b0e4ee3569de9b58dc9b91198681a1952e087434e67314e0b6ff29944a24afa4',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\StaffMutationRowVersionContractTest.php' => 'b23bb16329abfec2a949fc3ca15a8284668c5e54be76bd2f111d6f4616993725',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\StaffReservationOperationGuardTest.php' => 'f504857853175bec3a0be6ee99ceb37d8d580a7763150fefe018a02f72dab5ef',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\TableReleaseGuardTest.php' => '2d46676163c1f30a154e1e913f18db6a202ce8df0d4f5554e91c29b0bbd3f8ec',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\TableStateAuditLoggerTest.php' => '5eda7805598560ad0b08f36df93ad63a95682ed62e781159c4af8ab5780d52b8',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\VoucherRedemptionSupportTest.php' => '4bc9f381bb0703e70928fab06cde5a4be20c130aca28dee17f74e9c02520a8fb',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\VoucherUsageGuardTest.php' => '7bb2863a75c654d74028852ceeb356997ad9c88f52d1da8759f2964b77b977de',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Unit\\Support\\WaitingListStateMachineTest.php' => '7600ad5dcc07567c35b9e56085a0e7e0510bd4861c22445ba646c72a0338340e',
  ),
  'composerLocks' => 
  array (
    'C:/Users/Duong Vinh/RestaurantPOS-Laravel/composer.lock' => 'b696fef15b54e6af5f6f9f745bb71a856fad3ee317874f627eabfb9bd2d6e39b',
  ),
  'composerInstalled' => 
  array (
    'C:/Users/Duong Vinh/RestaurantPOS-Laravel/vendor/composer/installed.php' => 
    array (
      'versions' => 
      array (
        'brick/math' => 
        array (
          'pretty_version' => '0.14.8',
          'version' => '0.14.8.0',
          'reference' => '63422359a44b7f06cae63c3b429b59e8efcc0629',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../brick/math',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'carbonphp/carbon-doctrine-types' => 
        array (
          'pretty_version' => '3.2.0',
          'version' => '3.2.0.0',
          'reference' => '18ba5ddfec8976260ead6e866180bd5d2f71aa1d',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../carbonphp/carbon-doctrine-types',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'cordoval/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'davedevelopment/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'dflydev/dot-access-data' => 
        array (
          'pretty_version' => 'v3.0.3',
          'version' => '3.0.3.0',
          'reference' => 'a23a2bf4f31d3518f3ecb38660c95715dfead60f',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../dflydev/dot-access-data',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'doctrine/dbal' => 
        array (
          'pretty_version' => '4.4.3',
          'version' => '4.4.3.0',
          'reference' => '61e730f1658814821a85f2402c945f3883407dec',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../doctrine/dbal',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'doctrine/deprecations' => 
        array (
          'pretty_version' => '1.1.6',
          'version' => '1.1.6.0',
          'reference' => 'd4fe3e6fd9bb9e72557a19674f44d8ac7db4c6ca',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../doctrine/deprecations',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'doctrine/inflector' => 
        array (
          'pretty_version' => '2.1.0',
          'version' => '2.1.0.0',
          'reference' => '6d6c96277ea252fc1304627204c3d5e6e15faa3b',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../doctrine/inflector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'doctrine/lexer' => 
        array (
          'pretty_version' => '3.0.1',
          'version' => '3.0.1.0',
          'reference' => '31ad66abc0fc9e1a1f2d9bc6a42668d2fbbcd6dd',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../doctrine/lexer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'dragonmantank/cron-expression' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => 'd61a8a9604ec1f8c3d150d09db6ce98b32675013',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../dragonmantank/cron-expression',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'egulias/email-validator' => 
        array (
          'pretty_version' => '4.0.4',
          'version' => '4.0.4.0',
          'reference' => 'd42c8731f0624ad6bdc8d3e5e9a4524f68801cfa',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../egulias/email-validator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'fakerphp/faker' => 
        array (
          'pretty_version' => 'v1.24.1',
          'version' => '1.24.1.0',
          'reference' => 'e0ee18eb1e6dc3cda3ce9fd97e5a0689a88a64b5',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../fakerphp/faker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'filp/whoops' => 
        array (
          'pretty_version' => '2.18.4',
          'version' => '2.18.4.0',
          'reference' => 'd2102955e48b9fd9ab24280a7ad12ed552752c4d',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../filp/whoops',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'fruitcake/php-cors' => 
        array (
          'pretty_version' => 'v1.4.0',
          'version' => '1.4.0.0',
          'reference' => '38aaa6c3fd4c157ffe2a4d10aa8b9b16ba8de379',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../fruitcake/php-cors',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'graham-campbell/result-type' => 
        array (
          'pretty_version' => 'v1.1.4',
          'version' => '1.1.4.0',
          'reference' => 'e01f4a821471308ba86aa202fed6698b6b695e3b',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../graham-campbell/result-type',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/guzzle' => 
        array (
          'pretty_version' => '7.10.0',
          'version' => '7.10.0.0',
          'reference' => 'b51ac707cfa420b7bfd4e4d5e510ba8008e822b4',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../guzzlehttp/guzzle',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/promises' => 
        array (
          'pretty_version' => '2.3.0',
          'version' => '2.3.0.0',
          'reference' => '481557b130ef3790cf82b713667b43030dc9c957',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../guzzlehttp/promises',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/psr7' => 
        array (
          'pretty_version' => '2.9.0',
          'version' => '2.9.0.0',
          'reference' => '7d0ed42f28e42d61352a7a79de682e5e67fec884',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../guzzlehttp/psr7',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/uri-template' => 
        array (
          'pretty_version' => 'v1.0.5',
          'version' => '1.0.5.0',
          'reference' => '4f4bbd4e7172148801e76e3decc1e559bdee34e1',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../guzzlehttp/uri-template',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'hamcrest/hamcrest-php' => 
        array (
          'pretty_version' => 'v2.1.1',
          'version' => '2.1.1.0',
          'reference' => 'f8b1c0173b22fa6ec77a81fe63e5b01eba7e6487',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../hamcrest/hamcrest-php',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'iamcal/sql-parser' => 
        array (
          'pretty_version' => 'v0.7',
          'version' => '0.7.0.0',
          'reference' => '610392f38de49a44dab08dc1659960a29874c4b8',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../iamcal/sql-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'illuminate/auth' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/broadcasting' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/bus' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/cache' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/collections' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/concurrency' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/conditionable' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/config' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/console' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/container' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/contracts' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/cookie' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/database' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/encryption' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/events' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/filesystem' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/hashing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/http' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/json-schema' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/log' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/macroable' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/mail' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/notifications' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/pagination' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/pipeline' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/process' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/queue' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/redis' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/reflection' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/routing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/session' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/support' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/testing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/translation' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/validation' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'illuminate/view' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.56.0',
          ),
        ),
        'kodova/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'larastan/larastan' => 
        array (
          'pretty_version' => 'v3.9.3',
          'version' => '3.9.3.0',
          'reference' => '64a52bcc5347c89fdf131cb59f96ebfbc8d1ad65',
          'type' => 'phpstan-extension',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../larastan/larastan',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/framework' => 
        array (
          'pretty_version' => 'v12.56.0',
          'version' => '12.56.0.0',
          'reference' => 'dac16d424b59debb2273910dde88eb7050a2a709',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/framework',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/pail' => 
        array (
          'pretty_version' => 'v1.2.6',
          'version' => '1.2.6.0',
          'reference' => 'aa71a01c309e7f66bc2ec4fb1a59291b82eb4abf',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/pail',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/pint' => 
        array (
          'pretty_version' => 'v1.29.0',
          'version' => '1.29.0.0',
          'reference' => 'bdec963f53172c5e36330f3a400604c69bf02d39',
          'type' => 'project',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/pint',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/prompts' => 
        array (
          'pretty_version' => 'v0.3.16',
          'version' => '0.3.16.0',
          'reference' => '11e7d5f93803a2190b00e145142cb00a33d17ad2',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/prompts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/sail' => 
        array (
          'pretty_version' => 'v1.56.0',
          'version' => '1.56.0.0',
          'reference' => 'f43426bb42a1cb7a51a3861d9138063e54766d28',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/sail',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/serializable-closure' => 
        array (
          'pretty_version' => 'v2.0.11',
          'version' => '2.0.11.0',
          'reference' => 'd1af40ac4a6ccc12bd062a7184f63c9995a63bdd',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/serializable-closure',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/tinker' => 
        array (
          'pretty_version' => 'v2.11.1',
          'version' => '2.11.1.0',
          'reference' => 'c9f80cc835649b5c1842898fb043f8cc098dd741',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../laravel/tinker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/commonmark' => 
        array (
          'pretty_version' => '2.8.2',
          'version' => '2.8.2.0',
          'reference' => '59fb075d2101740c337c7216e3f32b36c204218b',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/commonmark',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/config' => 
        array (
          'pretty_version' => 'v1.2.0',
          'version' => '1.2.0.0',
          'reference' => '754b3604fb2984c71f4af4a9cbe7b57f346ec1f3',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/config',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/flysystem' => 
        array (
          'pretty_version' => '3.33.0',
          'version' => '3.33.0.0',
          'reference' => '570b8871e0ce693764434b29154c54b434905350',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/flysystem',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/flysystem-local' => 
        array (
          'pretty_version' => '3.31.0',
          'version' => '3.31.0.0',
          'reference' => '2f669db18a4c20c755c2bb7d3a7b0b2340488079',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/flysystem-local',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/mime-type-detection' => 
        array (
          'pretty_version' => '1.16.0',
          'version' => '1.16.0.0',
          'reference' => '2d6702ff215bf922936ccc1ad31007edc76451b9',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/mime-type-detection',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/uri' => 
        array (
          'pretty_version' => '7.8.1',
          'version' => '7.8.1.0',
          'reference' => '08cf38e3924d4f56238125547b5720496fac8fd4',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/uri',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/uri-interfaces' => 
        array (
          'pretty_version' => '7.8.1',
          'version' => '7.8.1.0',
          'reference' => '85d5c77c5d6d3af6c54db4a78246364908f3c928',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../league/uri-interfaces',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'mockery/mockery' => 
        array (
          'pretty_version' => '1.6.12',
          'version' => '1.6.12.0',
          'reference' => '1f4efdd7d3beafe9807b08156dfcb176d18f1699',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../mockery/mockery',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'monolog/monolog' => 
        array (
          'pretty_version' => '3.10.0',
          'version' => '3.10.0.0',
          'reference' => 'b321dd6749f0bf7189444158a3ce785cc16d69b0',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../monolog/monolog',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'mtdowling/cron-expression' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '^1.0',
          ),
        ),
        'myclabs/deep-copy' => 
        array (
          'pretty_version' => '1.13.4',
          'version' => '1.13.4.0',
          'reference' => '07d290f0c47959fd5eed98c95ee5602db07e0b6a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../myclabs/deep-copy',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nesbot/carbon' => 
        array (
          'pretty_version' => '3.11.4',
          'version' => '3.11.4.0',
          'reference' => 'e890471a3494740f7d9326d72ce6a8c559ffee60',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nesbot/carbon',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nette/schema' => 
        array (
          'pretty_version' => 'v1.3.5',
          'version' => '1.3.5.0',
          'reference' => 'f0ab1a3cda782dbc5da270d28545236aa80c4002',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nette/schema',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nette/utils' => 
        array (
          'pretty_version' => 'v4.1.3',
          'version' => '4.1.3.0',
          'reference' => 'bb3ea637e3d131d72acc033cfc2746ee893349fe',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nette/utils',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nikic/php-parser' => 
        array (
          'pretty_version' => 'v5.7.0',
          'version' => '5.7.0.0',
          'reference' => 'dca41cd15c2ac9d055ad70dbfd011130757d1f82',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nikic/php-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nunomaduro/collision' => 
        array (
          'pretty_version' => 'v8.9.3',
          'version' => '8.9.3.0',
          'reference' => 'b0d8ab95b29c3189aeeb902d81215231df4c1b64',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nunomaduro/collision',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nunomaduro/termwind' => 
        array (
          'pretty_version' => 'v2.4.0',
          'version' => '2.4.0.0',
          'reference' => '712a31b768f5daea284c2169a7d227031001b9a8',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../nunomaduro/termwind',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'phar-io/manifest' => 
        array (
          'pretty_version' => '2.0.4',
          'version' => '2.0.4.0',
          'reference' => '54750ef60c58e43759730615a392c31c80e23176',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phar-io/manifest',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phar-io/version' => 
        array (
          'pretty_version' => '3.2.1',
          'version' => '3.2.1.0',
          'reference' => '4f7fd7836c6f332bb2933569e566a0d6c4cbed74',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phar-io/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpoption/phpoption' => 
        array (
          'pretty_version' => '1.9.5',
          'version' => '1.9.5.0',
          'reference' => '75365b91986c2405cf5e1e012c5595cd487a98be',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpoption/phpoption',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'phpstan/phpstan' => 
        array (
          'pretty_version' => '2.1.46',
          'version' => '2.1.46.0',
          'reference' => 'a193923fc2d6325ef4e741cf3af8c3e8f54dbf25',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpstan/phpstan',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-code-coverage' => 
        array (
          'pretty_version' => '11.0.12',
          'version' => '11.0.12.0',
          'reference' => '2c1ed04922802c15e1de5d7447b4856de949cf56',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/php-code-coverage',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-file-iterator' => 
        array (
          'pretty_version' => '5.1.1',
          'version' => '5.1.1.0',
          'reference' => '2f3a64888c814fc235386b7387dd5b5ed92ad903',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/php-file-iterator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-invoker' => 
        array (
          'pretty_version' => '5.0.1',
          'version' => '5.0.1.0',
          'reference' => 'c1ca3814734c07492b3d4c5f794f4b0995333da2',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/php-invoker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-text-template' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '3e0404dc6b300e6bf56415467ebcb3fe4f33e964',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/php-text-template',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-timer' => 
        array (
          'pretty_version' => '7.0.1',
          'version' => '7.0.1.0',
          'reference' => '3b415def83fbcb41f991d9ebf16ae4ad8b7837b3',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/php-timer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/phpunit' => 
        array (
          'pretty_version' => '11.5.55',
          'version' => '11.5.55.0',
          'reference' => 'adc7262fccc12de2b30f12a8aa0b33775d814f00',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../phpunit/phpunit',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/cache' => 
        array (
          'pretty_version' => '3.0.0',
          'version' => '3.0.0.0',
          'reference' => 'aa5030cfa5405eccfdcb1083ce040c2cb8d253bf',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/cache',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/clock' => 
        array (
          'pretty_version' => '1.0.0',
          'version' => '1.0.0.0',
          'reference' => 'e41a24703d4560fd0acb709162f73b8adfc3aa0d',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/clock',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/clock-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/container' => 
        array (
          'pretty_version' => '2.0.2',
          'version' => '2.0.2.0',
          'reference' => 'c71ecc56dfe541dbd90c5360474fbc405f8d5963',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/container',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/container-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.1|2.0',
          ),
        ),
        'psr/event-dispatcher' => 
        array (
          'pretty_version' => '1.0.0',
          'version' => '1.0.0.0',
          'reference' => 'dbefd12671e8a14ec7f180cab83036ed26714bb0',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/event-dispatcher',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/event-dispatcher-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-client' => 
        array (
          'pretty_version' => '1.0.3',
          'version' => '1.0.3.0',
          'reference' => 'bb5906edc1c324c9a05aa0873d40117941e5fa90',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/http-client',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-client-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-factory' => 
        array (
          'pretty_version' => '1.1.0',
          'version' => '1.1.0.0',
          'reference' => '2b4765fddfe3b508ac62f829e852b1501d3f6e8a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/http-factory',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-factory-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-message' => 
        array (
          'pretty_version' => '2.0',
          'version' => '2.0.0.0',
          'reference' => '402d35bcb92c70c026d1a6a9883f06b2ead23d71',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/http-message',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-message-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/log' => 
        array (
          'pretty_version' => '3.0.2',
          'version' => '3.0.2.0',
          'reference' => 'f16e1d5863e37f8d8c2a01719f5b34baa2b714d3',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/log',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/log-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0|2.0|3.0',
            1 => '3.0.0',
          ),
        ),
        'psr/simple-cache' => 
        array (
          'pretty_version' => '3.0.0',
          'version' => '3.0.0.0',
          'reference' => '764e0b3939f5ca87cb904f570ef9be2d78a07865',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psr/simple-cache',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/simple-cache-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0|2.0|3.0',
          ),
        ),
        'psy/psysh' => 
        array (
          'pretty_version' => 'v0.12.22',
          'version' => '0.12.22.0',
          'reference' => '3be75d5b9244936dd4ac62ade2bfb004d13acf0f',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../psy/psysh',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ralouphie/getallheaders' => 
        array (
          'pretty_version' => '3.0.3',
          'version' => '3.0.3.0',
          'reference' => '120b605dfeb996808c31b6477290a714d356e822',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../ralouphie/getallheaders',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ramsey/collection' => 
        array (
          'pretty_version' => '2.1.1',
          'version' => '2.1.1.0',
          'reference' => '344572933ad0181accbf4ba763e85a0306a8c5e2',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../ramsey/collection',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ramsey/uuid' => 
        array (
          'pretty_version' => '4.9.2',
          'version' => '4.9.2.0',
          'reference' => '8429c78ca35a09f27565311b98101e2826affde0',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../ramsey/uuid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'reliese/laravel' => 
        array (
          'pretty_version' => 'v1.4.0',
          'version' => '1.4.0.0',
          'reference' => '2181113d420cae67ec68b6bbe6f325900856d6b9',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../reliese/laravel',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'rhumsaa/uuid' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '4.9.2',
          ),
        ),
        'sebastian/cli-parser' => 
        array (
          'pretty_version' => '3.0.2',
          'version' => '3.0.2.0',
          'reference' => '15c5dd40dc4f38794d383bb95465193f5e0ae180',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/cli-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/code-unit' => 
        array (
          'pretty_version' => '3.0.3',
          'version' => '3.0.3.0',
          'reference' => '54391c61e4af8078e5b276ab082b6d3c54c9ad64',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/code-unit',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/code-unit-reverse-lookup' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '183a9b2632194febd219bb9246eee421dad8d45e',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/code-unit-reverse-lookup',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/comparator' => 
        array (
          'pretty_version' => '6.3.3',
          'version' => '6.3.3.0',
          'reference' => '2c95e1e86cb8dd41beb8d502057d1081ccc8eca9',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/comparator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/complexity' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => 'ee41d384ab1906c68852636b6de493846e13e5a0',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/complexity',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/diff' => 
        array (
          'pretty_version' => '6.0.2',
          'version' => '6.0.2.0',
          'reference' => 'b4ccd857127db5d41a5b676f24b51371d76d8544',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/diff',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/environment' => 
        array (
          'pretty_version' => '7.2.1',
          'version' => '7.2.1.0',
          'reference' => 'a5c75038693ad2e8d4b6c15ba2403532647830c4',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/environment',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/exporter' => 
        array (
          'pretty_version' => '6.3.2',
          'version' => '6.3.2.0',
          'reference' => '70a298763b40b213ec087c51c739efcaa90bcd74',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/exporter',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/global-state' => 
        array (
          'pretty_version' => '7.0.2',
          'version' => '7.0.2.0',
          'reference' => '3be331570a721f9a4b5917f4209773de17f747d7',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/global-state',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/lines-of-code' => 
        array (
          'pretty_version' => '3.0.1',
          'version' => '3.0.1.0',
          'reference' => 'd36ad0d782e5756913e42ad87cb2890f4ffe467a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/lines-of-code',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-enumerator' => 
        array (
          'pretty_version' => '6.0.1',
          'version' => '6.0.1.0',
          'reference' => 'f5b498e631a74204185071eb41f33f38d64608aa',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/object-enumerator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-reflector' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '6e1a43b411b2ad34146dee7524cb13a068bb35f9',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/object-reflector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/recursion-context' => 
        array (
          'pretty_version' => '6.0.3',
          'version' => '6.0.3.0',
          'reference' => 'f6458abbf32a6c8174f8f26261475dc133b3d9dc',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/recursion-context',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/type' => 
        array (
          'pretty_version' => '5.1.3',
          'version' => '5.1.3.0',
          'reference' => 'f77d2d4e78738c98d9a68d2596fe5e8fa380f449',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/type',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/version' => 
        array (
          'pretty_version' => '5.0.2',
          'version' => '5.0.2.0',
          'reference' => 'c687e3387b99f5b03b6caa64c74b63e2936ff874',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../sebastian/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'spatie/once' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'staabm/side-effects-detector' => 
        array (
          'pretty_version' => '1.0.5',
          'version' => '1.0.5.0',
          'reference' => 'd8334211a140ce329c13726d4a715adbddd0a163',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../staabm/side-effects-detector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/clock' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '674fa3b98e21531dd040e613479f5f6fa8f32111',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/clock',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/console' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '1e92e39c51f95b88e3d66fa2d9f06d1fb45dd707',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/console',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/css-selector' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => 'b055f228a4178a1d6774909903905e3475f3eac8',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/css-selector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/deprecation-contracts' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => '63afe740e99a13ba87ec199bb07bbdee937a5b62',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/deprecation-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/error-handler' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '8dd79d8af777ee6cba2fd4d98da6ffb839f3c0fa',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/error-handler',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => 'f57b899fa736fd71121168ef268f23c206083f0a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/event-dispatcher',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher-contracts' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => '59eb412e93815df44f05f342958efa9f46b1e586',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/event-dispatcher-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '2.0|3.0',
          ),
        ),
        'symfony/finder' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => 'e0be088d22278583a82da281886e8c3592fbf149',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/finder',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/http-foundation' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '9381209597ec66c25be154cbf2289076e64d1eab',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/http-foundation',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/http-kernel' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '017e76ad089bac281553389269e259e155935e1a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/http-kernel',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/mailer' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => 'f6ea532250b476bfc1b56699b388a1bdbf168f62',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/mailer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/mime' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '6df02f99998081032da3407a8d6c4e1dcb5d4379',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/mime',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-ctype' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => 'a3cc8b044a6ea513310cbd48ef7333b384945638',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-ctype',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-grapheme' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '380872130d3a5dd3ace2f4010d95125fde5d5c70',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-intl-grapheme',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-idn' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '9614ac4d8061dc257ecc64cba1b140873dce8ad3',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-intl-idn',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-normalizer' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '3833d7255cc303546435cb650316bff708a1c75c',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-intl-normalizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-mbstring' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '6d857f4d76bd4b343eac26d6b539585d2bc56493',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-mbstring',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php80' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '0cc9dd0f17f61d8131e7df6b84bd344899fe2608',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-php80',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php83' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '17f6f9a6b1735c0f163024d959f700cfbc5155e5',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-php83',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php84' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => 'd8ced4d875142b6a7426000426b8abc631d6b191',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-php84',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php85' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => 'd4e5fcd4ab3d998ab16c0db48e6cbb9a01993f91',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-php85',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-uuid' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '21533be36c24be3f4b1669c4725c7d1d2bab4ae2',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/polyfill-uuid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/process' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '60f19cd3badc8de688421e21e4305eba50f8089a',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/process',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/routing' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '9608de9873ec86e754fb6c0a0fa7e5f1a960eb6b',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/routing',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/service-contracts' => 
        array (
          'pretty_version' => 'v3.6.1',
          'version' => '3.6.1.0',
          'reference' => '45112560a3ba2d715666a509a0bc9521d10b6c43',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/service-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/string' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '114ac57257d75df748eda23dd003878080b8e688',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/string',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '33600f8489485425bfcddd0d983391038d3422e7',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/translation',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation-contracts' => 
        array (
          'pretty_version' => 'v3.6.1',
          'version' => '3.6.1.0',
          'reference' => '65a8bc82080447fae78373aa10f8d13b38338977',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/translation-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '2.3|3.0',
          ),
        ),
        'symfony/uid' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '6883ebdf7bf6a12b37519dbc0df62b0222401b56',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/uid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/var-dumper' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => '9510c3966f749a1d1ff0059e1eabef6cc621e7fd',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/var-dumper',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/yaml' => 
        array (
          'pretty_version' => 'v7.4.8',
          'version' => '7.4.8.0',
          'reference' => 'c58fdf7b3d6c2995368264c49e4e8b05bcff2883',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../symfony/yaml',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'theseer/tokenizer' => 
        array (
          'pretty_version' => '1.3.1',
          'version' => '1.3.1.0',
          'reference' => 'b7489ce515e168639d17feec34b8847c326b0b3c',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../theseer/tokenizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'tijsverkoyen/css-to-inline-styles' => 
        array (
          'pretty_version' => 'v2.4.0',
          'version' => '2.4.0.0',
          'reference' => 'f0292ccf0ec75843d65027214426b6b163b48b41',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../tijsverkoyen/css-to-inline-styles',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'vlucas/phpdotenv' => 
        array (
          'pretty_version' => 'v5.6.3',
          'version' => '5.6.3.0',
          'reference' => '955e7815d677a3eaa7075231212f2110983adecc',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../vlucas/phpdotenv',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'voku/portable-ascii' => 
        array (
          'pretty_version' => '2.0.3',
          'version' => '2.0.3.0',
          'reference' => 'b1d923f88091c6bf09699efcd7c8a1b1bfd7351d',
          'type' => 'library',
          'install_path' => 'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\composer/../voku/portable-ascii',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
      ),
    ),
  ),
  'executedFilesHashes' => 
  array (
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tools\\phpstan\\bootstrap.php' => '40c6df5dec7fa46a785a6494095a8165883e5c33f63901fc1caed6f037d9f5f6',
    'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\larastan\\larastan\\bootstrap.php' => '5a3eacbf63b3e41659adfee92facededf8e020a932800f93c9a8b0e67f235805',
    'phar://C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\phpstan\\phpstan\\phpstan.phar\\stubs\\runtime\\Attribute85.php' => 'cb8b31e82c61ce197871c9e8a6f122256751f2ab606dd2be90846d4fa5f8933e',
    'phar://C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\phpstan\\phpstan\\phpstan.phar\\stubs\\runtime\\ReflectionAttribute.php' => 'c0068e383717870a304781d462f7e2afe1c6f24e9133851852a2aca96b4fa26f',
    'phar://C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\phpstan\\phpstan\\phpstan.phar\\stubs\\runtime\\ReflectionIntersectionType.php' => '65fe0a8bc6fe285d8ddc8798ab5b9299920af70db5ad74596bc08df823e7c5d9',
    'phar://C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\vendor\\phpstan\\phpstan\\phpstan.phar\\stubs\\runtime\\ReflectionUnionType.php' => '1e2fe940e4ba4e00d9ee6adb2af3ee1bf333e6f8afe61c61deb038886d293427',
  ),
  'phpExtensions' => 
  array (
    0 => 'Core',
    1 => 'FFI',
    2 => 'PDO',
    3 => 'Phar',
    4 => 'Reflection',
    5 => 'SPL',
    6 => 'SimpleXML',
    7 => 'Zend OPcache',
    8 => 'bcmath',
    9 => 'bz2',
    10 => 'calendar',
    11 => 'ctype',
    12 => 'curl',
    13 => 'date',
    14 => 'dba',
    15 => 'dom',
    16 => 'exif',
    17 => 'fileinfo',
    18 => 'filter',
    19 => 'ftp',
    20 => 'gd',
    21 => 'hash',
    22 => 'iconv',
    23 => 'igbinary',
    24 => 'json',
    25 => 'libxml',
    26 => 'mbstring',
    27 => 'mysqli',
    28 => 'mysqlnd',
    29 => 'openssl',
    30 => 'pcre',
    31 => 'pdo_mysql',
    32 => 'pdo_sqlite',
    33 => 'random',
    34 => 'redis',
    35 => 'session',
    36 => 'shmop',
    37 => 'soap',
    38 => 'sockets',
    39 => 'sqlite3',
    40 => 'standard',
    41 => 'sysvshm',
    42 => 'tokenizer',
    43 => 'xml',
    44 => 'xmlreader',
    45 => 'xmlwriter',
    46 => 'zip',
    47 => 'zlib',
  ),
  'stubFiles' => 
  array (
  ),
  'level' => '0',
),
	'projectExtensionFiles' => array (
),
	'errorsCallback' => static function (): array { return array (
); },
	'locallyIgnoredErrorsCallback' => static function (): array { return array (
); },
	'linesToIgnore' => array (
),
	'unmatchedLineIgnores' => array (
),
	'collectedDataCallback' => static function (): array { return array (
); },
	'dependencies' => array (
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListOwnerContractHttpFlowTest.php' => 
  array (
    'fileHash' => '79d79ff6741d158aeec7852e0d1f7bbd66d981d45dce16192aeb61dd0db79a8c',
    'dependentFiles' => 
    array (
    ),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListOwnerResponseFlowTest.php' => 
  array (
    'fileHash' => 'cf1efdc7b06110e317b3c0d19608bb568e2a173215c225b1a3aacd0d9f057e81',
    'dependentFiles' => 
    array (
    ),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListSelfServiceHttpFlowTest.php' => 
  array (
    'fileHash' => '2c27e4dbf8c95a665af1bd1c7b34ff7300215dd09fc68c2b5285bcdbf4336fd5',
    'dependentFiles' => 
    array (
    ),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\WaitingListRuntimeContractDriftTest.php' => 
  array (
    'fileHash' => 'a3b73d4742b5fe608916f35ae945947a63e3f6a30609dc470eebe19d56508322',
    'dependentFiles' => 
    array (
    ),
  ),
),
	'exportedNodesCallback' => static function (): array { return array (
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListOwnerContractHttpFlowTest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Tests\\Feature\\WaitingList\\CustomerWaitingListOwnerContractHttpFlowTest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Tests\\TestCase',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Tests\\Support\\BuildsBookingScenario',
        1 => 'Illuminate\\Foundation\\Testing\\DatabaseTransactions',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'setUp',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_authenticated_owner_can_create_show_and_list_waiting_entries_with_canonical_owner_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_guest_session_headers_are_rejected_for_owner_only_waiting_list_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_active_customer_access_session_id_without_customer_token_is_forbidden_for_owner_only_waiting_list_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_pre_resolved_staff_user_is_rejected_under_owner_only_waiting_list_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_accept_notified_entry_within_open_window_using_canonical_owner_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_decline_cancels_entry_instead_of_returning_it_to_waiting_and_releases_notify_hold',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_confirm_arrival_returns_staff_seat_meta_without_legacy_lifecycle_payload_or_persisted_customer_response_fields',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_non_owner_show_and_mutation_follow_current_access_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        9 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_cannot_accept_or_confirm_arrival_after_notify_window_has_expired',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        10 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_cannot_accept_or_confirm_when_entry_is_not_in_notified_state',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        11 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_cancel_owned_waiting_entry_with_row_version',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        12 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_create_rejects_when_active_waiting_entry_already_exists',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        13 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_expired_customer_access_session_is_rejected_for_owner_only_waiting_list_routes_even_with_session_header',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        14 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_unauthenticated_request_is_rejected',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListOwnerResponseFlowTest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Tests\\Feature\\WaitingList\\CustomerWaitingListOwnerResponseFlowTest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Tests\\TestCase',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Tests\\Support\\AssertsAuditTrail',
        1 => 'Tests\\Support\\BuildsBookingScenario',
        2 => 'Illuminate\\Foundation\\Testing\\DatabaseTransactions',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'setUp',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_list_and_show_only_owned_waiting_entries',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_accept_notified_entry_within_open_window',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_decline_notified_entry_and_active_hold_is_cancelled',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_can_cancel_waiting_entry_before_notify',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_response_rejects_non_owner_expired_window_and_invalid_state',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\CustomerWaitingListSelfServiceHttpFlowTest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Tests\\Feature\\WaitingList\\CustomerWaitingListSelfServiceHttpFlowTest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Tests\\TestCase',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Tests\\Support\\BuildsBookingScenario',
        1 => 'Illuminate\\Foundation\\Testing\\DatabaseTransactions',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'setUp',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_legacy_guest_session_self_service_contract_is_rejected_under_owner_only_waiting_list_flow',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_resource_uses_canonical_shape_and_omits_legacy_self_service_lifecycle_fields',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_owner_decline_canonical_semantics_cancel_entry_instead_of_returning_to_waiting',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\tests\\Feature\\WaitingList\\WaitingListRuntimeContractDriftTest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Tests\\Feature\\WaitingList\\WaitingListRuntimeContractDriftTest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Tests\\TestCase',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'test_customer_waiting_list_routes_stay_on_canonical_owner_only_runtime_contract',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
); },
];

<?php declare(strict_types = 1);

// ftm-C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffCheckoutService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      '5059d6823e70cf790a7d47132c73af69' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'd27a30d38d3d5b1dd9a753226364b45f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8af9d563b0b2fbe1844974f6f84e8b6c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'checkout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'cef9f6f659bea33db3afbadcd8fc7e0f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'buildCheckoutResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '306d34e951796efcaf848e7f9181c257' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'lockBill',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '458374780ea244d26920c65e00803f15' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'closeOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '909c88d70081ac9b7355e1a28c0732a0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'previewSettlement',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'fd7df55f36b46f45488628b539de53d9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'previewRefund',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f5853e701c15db18ca307d4219a644b0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'payOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '724d1c8b342f25695434b052865c0379' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'refundReservation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'ad293f42fd4c039ccb80d1b496413a50' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'refundAndCancelReservation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '0a46ff471a7651dcfdf7fddfe64f5da6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'lockBillLocked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c92e3141c2bd2be511ff6ab1c0fad400' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'payOrderLocked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '2f8931f3e18cb98ce91fa3bd47f2e9f7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'executeRefundFlow',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'd96aed48fa85e02ecee5ee3a7d621d78' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'assertExpectedOrderRowVersion',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '08a4785d8c96c6ea72c7ea0bc4f273e5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'getReservationLockContextForOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5c8a0dd5cfecacb189df74e4a262e516' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'getReservationLockContextForReservation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '807ce5157e30318fd0809a91f2f6a898' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'computeReservationBillSnapshot',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8d180ca878e70058c06e9f6219fc3df8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'completeReservationSettlement',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '17fa9f71027408f43e98ad591e7d7749' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'buildSettlementAmounts',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3e06473f31d751548d705e362540a9b1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'attachTotals',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '12395ebc9022c34926215463c82135fb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'allocateRefundPaymentsBySource',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'db0b38a3866ffaf84a32695427f5f426' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'buildRefundPlan',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'fff637f545b215e62725c3be04d5c84a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'assertRefundableStatus',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'e7d7751c3cf05b761c1c5846a70efc54' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'resolveRefundCurrency',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5b14a797f1682d534fe222923ce26fc7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'normalizeCurrencyCode',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '53c2e656ffe9d5751bb033a76cb3ab44' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'assertPaymentsSingleCurrency',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'e402f1dd11a0e496aa7f2388bd85c002' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'makeDerivedKey',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5b3643a1bc8cdd313898cc45878539fe' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'syncDepositSnapshot',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '0256fdfa1c089ad974688ac4d9c76a8d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'resolveDepositStatus',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'b47e3fce229e7f2bfa0d0b1783b06cb1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'cancelReservationLocked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3ea045bf0f3f220a87bf5a19585e6e18' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'cancelActiveOrders',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7c6e6d2e8601d8e85e975d16d18ac40c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'releaseTables',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5aded111a69f06c8cb445fb31d9ce9f1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'buildRefundResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'ff8a459e80faf821e0657244eaa167cf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'releaseAppliedVoucherLocked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a00122a3bb8c1881f66610193b66a571' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'consumeAppliedVoucherLocked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c11c89c13f95d7b5e8be7d490ee4ff7a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'currentVoucherDiscountAmount',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '0cebc5d4eaad39ae89756cddd3a9f9bf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'currentLoyaltyDiscountAmount',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '78a88cdc8810f09487d60a5160355f74' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'paymentReplayCacheKey',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '189c6459e8be11a74399a982161453ec' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'paymentReplayCacheGet',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '47e4c2447fc8ac3460327845fe47de43' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'paymentReplayCachePut',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'dbe98ad2f80561e894120af178ced858' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'findExistingCheckoutReplay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a9b84c163320ff39594e25e800ad2285' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'findExistingOrderPaymentReplay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '0a40e9e46f669d622118073a383edeb3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'findExistingRefundReplay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '4b05995c2f9d5963fe281de23f9cfe9b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'isDuplicatePaymentIdempotencyConstraint',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'fc8d2210f50da7edde5098117525b6d6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Services\\Staff',
         'uses' => 
        array (
          'paymentstatus' => 'App\\Enums\\PaymentStatus',
          'reservationorderstatus' => 'App\\Enums\\ReservationOrderStatus',
          'reservationordertype' => 'App\\Enums\\ReservationOrderType',
          'reservationstatus' => 'App\\Enums\\ReservationStatus',
          'payment' => 'App\\Models\\Payment',
          'reservation' => 'App\\Models\\Reservation',
          'reservationorder' => 'App\\Models\\ReservationOrder',
          'reservationorderitem' => 'App\\Models\\ReservationOrderItem',
          'uservoucher' => 'App\\Models\\UserVoucher',
          'loyaltypointsservice' => 'App\\Services\\LoyaltyPointsService',
          'notificationoutboxservice' => 'App\\Services\\NotificationOutboxService',
          'reservationfinancialsyncservice' => 'App\\Services\\ReservationFinancialSyncService',
          'reservationlockservice' => 'App\\Services\\ReservationLockService',
          'restauranttablestateservice' => 'App\\Services\\RestaurantTableStateService',
          'auditevent' => 'App\\Support\\AuditEvent',
          'databasewriteconflictmapper' => 'App\\Support\\DatabaseWriteConflictMapper',
          'paymentsummary' => 'App\\Support\\PaymentSummary',
          'reservationvoucherlifecyclesupport' => 'App\\Support\\ReservationVoucherLifecycleSupport',
          'voucherredemptionsupport' => 'App\\Support\\VoucherRedemptionSupport',
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'cache' => 'Illuminate\\Support\\Facades\\Cache',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'validationexception' => 'Illuminate\\Validation\\ValidationException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Services\\Staff\\StaffCheckoutService',
         'functionName' => 'throwIfDuplicatePaymentConstraint',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      'C:\\Users\\Duong Vinh\\RestaurantPOS-Laravel\\app\\Services\\Staff\\StaffCheckoutService.php' => 'dcaa78f8f73cc48f82cc3e2d8ba261254c71619a70d3e10bbb7f8684b195feb6',
    ),
  ),
));
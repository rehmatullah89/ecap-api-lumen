<?php
/**
 * Created by PhpStorm.
 * User: Magid Mroueh
 * Date: 3/21/18
 * Time: 3:40 PM
 */

// Constants
define('STATUS_INACTIVE', 0);
define('STATUS_ACTIVE', 1);

// Order Statuses
define('ORDER_STATUS_PENDING', 1);
define('ORDER_STATUS_WAITING_FOR_APPROVAL', 2); // Customer
define('ORDER_STATUS_CUSTOMER_APPROVED', 3);
define('ORDER_STATUS_CALL_CENTER_PROGRESS', 4);
define('ORDER_STATUS_DRIVER_PICKED_UP', 5);
define('ORDER_STATUS_SCHEDULED', 6);
define('ORDER_STATUS_ON_THE_WAY', 7);
define('ORDER_STATUS_DELIVERED', 8);
define('ORDER_STATUS_REQUEST_FOR_CANCELLATION', 9);
define('ORDER_STATUS_CANCELLED', 10);
define('ORDER_STATUS_CUSTOMER_REJECTED', 11);


//defining the role slugs in constants
define('ROLE_ADMIN', 'admin');
define('ROLE_CALL_CENTER', 'call-center');
define('ROLE_CUSTOMER', 'customer');
define('ROLE_DRIVER', 'driver');




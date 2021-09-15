ShareASale Merchant API PHP SDK Client
==================

A PHP SDK for the **Share-A-Sale Merchant API**

ShareASale Merchant API Documentation: [https://account.shareasale.com/m-apiips.cfm](https://account.shareasale.com/m-apiips.cfm)

*(you must be logged into a valid Merchant Account to view)*

**NOTE:** This is **NOT** the Affiliate API!

Install
-------

Install [Composer](https://getcomposer.org/) and run the following command:

```
php composer require ooglek/shareasale-merchant-api-sdk
```


### Create the Object

Calling from your code:

```php
$sas = new ooglek\ShareASale\Client(
    '12345',             // MerchantId
    'rAnDoMsTuFf',       // Token
    'sUpErRaNd0mStUfF'   // Secret Key
);

// Returns an array with the Summary of your Merchant account Activity
$records = $sas->activitySummary(
    [
        'datestart' => '15/09/2021',
        'dateend' => '15/08/2021'
    ]
);
```


### Getters and Setters

You can magically get and set any properties in the class by calling their name prefixed with `get` or `set`.

```php
$sas->setVersion('3.0');
$sas->getHttpResponse();
```

### Action Methods

Any of the mentioned Actions are implemented magically and case insensitively.

```php
$sas->void(['date' => '15/09/2021', 'ordernumber' => 12345]);

$sas->balance();

$sas->todayataglance();
```

### Troubleshooting

You can use these properties to access the raw Guzzle request data, in case you are having issues.

* `$this->getHttpResponse()` // Guzzle Response Object
* `$this->getQuery()`        // Array of Query Parameters
* `$this->getHeaders()`      // Array of HTTP Headers
* `$this->getSig()`          // The string that is SHA256 encoded

ShareASale does not publish their error codes, so yeah, I don't know what your error code means either. Contact ShareASale.

Example
-------


## Implemented Service Methods

### Transaction Requests

Note: You may need to request special permission for some of these Transaction Actions

* **void**
* **edit**
* **find**
* **new**
* **reference**

### Report Requests

* **transactiondetail**
* **weeklyprogress**
* **affiliatetimespan**
* **activitysummary**
* **datafeeddownloads**
* **todayataglance**
* **staterevenue**
* **report-affiliate**
* **transactioneditreport**
* **transactionvoidreport**
* **apitokencount**
* **ledger**
* **affiliateTags**
* **balance**

### Maintenance Requests

* **bannerList**
* **bannerUpload**
* **bannerEdit**
* **dealList**
* **dealUpload**
* **dealEdit**
* **approveAffiliate**
* **declineAffiliate**
* **MassTagAffiliates**


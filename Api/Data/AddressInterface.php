<?php

namespace Webmecanik\Connector\Api\Data;

use Magento\Customer\Api\Data\AddressInterface as BaseAddressInterface;

interface AddressInterface extends BaseAddressInterface
{
    public const PHONE_CODE = 'phone';
    public const MOBILE_CODE = 'mobile';
    public const STREET_1_CODE = 'address1';
    public const STREET_2_CODE = 'address2';
    public const POSTCODE_CODE = 'zipcode';
    public const COUNTRY_CODE = 'country';
}

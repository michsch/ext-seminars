<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Bag;

/**
 * This aggregate class holds a bunch of registration objects and allows iterating over them.
 *
 * @extends AbstractBag<\Tx_Seminars_OldModel_Registration>
 */
class RegistrationBag extends AbstractBag
{
    /**
     * @var class-string<\Tx_Seminars_OldModel_Registration>
     */
    protected static $modelClassName = \Tx_Seminars_OldModel_Registration::class;

    /**
     * @var string the name of the main DB table from which we get the records for this bag
     */
    protected static $tableName = 'tx_seminars_attendances';
}
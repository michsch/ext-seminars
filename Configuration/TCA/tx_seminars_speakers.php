<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToInsertRecords('tx_seminars_speakers');

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/Speaker.gif',
        'searchFields' => 'title',
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden, title, organization, homepage, description, image, skills, notes, ' .
            'address, phone_work, phone_home, phone_mobile, email, cancelation_period, owner',
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required,trim',
            ],
        ],
        'gender' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.gender',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', '0'],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.gender_male',
                        '1',
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.gender_female',
                        '2',
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'organization' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.organization',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'homepage' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.homepage',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 15,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'description' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.description',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'enableRichtext' => true,
            ],
        ],
        'image' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.image',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                'image',
                [
                    'maxitems' => 1,
                    'appearance' => [
                        'collapseAll' => true,
                        'expandSingle' => true,
                        'useSortable' => false,
                        'enabledControls' => [
                            'hide' => false,
                        ],
                        'fileUploadAllowed' => true,
                    ],
                ]
            ),
        ],
        'skills' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.skills',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_skills',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_speakers_skills_mm',
            ],
        ],
        'notes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.notes',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'address' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.address',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'phone_work' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.phone_work',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'phone_home' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.phone_home',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'phone_mobile' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.phone_mobile',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'email' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.email',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,nospace',
            ],
        ],
        'cancelation_period' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_speakers.cancelation_period',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'max' => 3,
                'eval' => 'int',
                'range' => [
                    'upper' => 999,
                    'lower' => 0,
                ],
            ],
        ],
        'owner' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:owner_feuser',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'fe_users',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden, title, gender, organization, homepage, description, image, skills, notes, ' .
                'address, phone_work, phone_home, phone_mobile, email, cancelation_period, owner',
        ],
    ],
];

return $tca;

<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use Sys25\RnBase\Configuration\Processor as ConfigurationProcessor;

/**
 * This class is the base class for any kind of front-end editor, for example the event editor or the registration form.
 */
abstract class AbstractEditor extends AbstractView
{
    /**
     * @var \tx_mkforms_forms_Base
     */
    private $formCreator = null;

    /**
     * UID of the currently edited object, zero if the object is going to be a new database record
     *
     * @var int
     */
    private $objectUid = 0;

    /**
     * @var array<string, mixed>
     */
    private $formConfiguration = [];

    /**
     * @var bool whether the class ist used in test mode
     */
    private $isTestMode = false;

    /**
     * @var array<string, string|int> this is used to fake form values for testing
     */
    private $fakedFormValues = [];

    /**
     * @param int $uid UID of the currently edited object.
     *        For creating a new database record, $uid must be zero. $uid must not be < 0.
     */
    public function setObjectUid(int $uid): void
    {
        $this->objectUid = $uid;
    }

    /**
     * @return int UID of the currently edited object, zero if a new object is being created
     */
    public function getObjectUid(): int
    {
        return $this->objectUid;
    }

    /**
     * Sets the form configuration.
     *
     * @param array<string, mixed> $formConfiguration the form configuration, must not be empty
     */
    public function setFormConfiguration(array $formConfiguration): void
    {
        $this->formConfiguration = $formConfiguration;
    }

    public function getFormCreator(): ?\tx_mkforms_forms_Base
    {
        if ($this->formCreator === null) {
            $this->formCreator = $this->makeFormCreator();
        }

        return $this->formCreator;
    }

    /**
     * Enables the test mode. If this mode is activated, the FORMidable object
     * will not be used at all, instead the faked form values will be taken.
     */
    public function setTestMode(): void
    {
        $this->isTestMode = true;
    }

    /**
     * Checks whether the test mode is set.
     */
    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }

    /**
     * Returns the FE editor in HTML.
     *
     * Note that render() requires the FORMidable object to be initializable.
     * This means that the test mode must not be set when calling render().
     *
     * @return string HTML for the FE editor or an error view if the requested object
     *         is not editable for the current user
     */
    public function render(): string
    {
        return $this->getFormCreator()->render();
    }

    /**
     * Creates a FORMidable instance for the current UID and form configuration.
     * The UID must be of an existing seminar object.
     *
     * This function does nothing if this instance is running in test mode.
     *
     * @throws \BadMethodCallException
     */
    protected function makeFormCreator(): ?\tx_mkforms_forms_Base
    {
        if ($this->isTestMode()) {
            return null;
        }

        if (empty($this->formConfiguration)) {
            throw new \BadMethodCallException(
                'Please define the form configuration to use via $this->setFormConfiguration().',
                1333293139
            );
        }

        \tx_rnbase::load(\tx_mkforms_forms_Factory::class);
        $form = \tx_mkforms_forms_Factory::createForm(null);

        /**
         * Configuration instance for plugin data. Necessary for LABEL translation.
         *
         * @var ConfigurationProcessor $pluginConfiguration
         */
        $pluginConfiguration = \tx_rnbase::makeInstance(ConfigurationProcessor::class);
        $pluginConfiguration->init($this->conf, $this->cObj, 'mkforms', 'mkforms');

        // Initialize the form from TypoScript data and provide configuration for the plugin.
        $form->initFromTs(
            $this,
            $this->formConfiguration,
            ($this->getObjectUid() > 0) ? $this->getObjectUid() : false,
            $pluginConfiguration,
            'form.'
        );

        return $form;
    }

    /**
     * Returns a form value from the FORMidable object.
     *
     * Note: In test mode, this function will return faked values.
     *
     * @param string $key column name of the 'tx_seminars_seminars' table as key, must not be empty
     *
     * @return string|array form value or an empty string if the value does not exist
     */
    public function getFormValue(string $key)
    {
        if ($this->isTestMode) {
            $dataSource = $this->fakedFormValues;
        } else {
            $dataSource = $this->getFormCreator()->getDataHandler()->getFormData();
        }

        return $dataSource[$key] ?? '';
    }

    /**
     * Fakes a form data value that is usually provided by the FORMidable object.
     *
     * This function is for testing purposes.
     *
     * @param string $key column name of the 'tx_seminars_seminars' table as key, must not be empty
     * @param string|int $value faked value
     */
    public function setFakedFormValue(string $key, $value): void
    {
        $this->fakedFormValues[$key] = $value;
    }
}
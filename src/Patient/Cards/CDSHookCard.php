<?php

/**
 * Portal Card
 *
 * A class representing the Patient Portal card displayed on the MRD.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Robert Down <robertdown@live.com>
 * @copyright Copyright (c) 2022 Robert Down <robertdown@live.com
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Patient\Cards;

use OpenEMR\Events\Patient\Summary\Card\RenderEvent;
use OpenEMR\Events\Patient\Card\Card;
use OpenEMR\Events\Patient\Summary\Card\CardModel;
use OpenEMR\Events\Patient\Summary\Card\SectionEvent;

class CDSHookCard extends CardModel
{
    const TEMPLATE_FILE = 'patient/partials/cds_hook_patient_view.html.twig';

    const CARD_ID = 'cds_hook_patient_view_card';

    private $opts = [];

    /**
     * @var EventDispatcher
     */
    private $ed;

    public function __construct()
    {
        global $GLOBALS;
        $this->ed = $GLOBALS['kernel']->getEventDispatcher();

        $this->setOpts();
        parent::__construct($this->opts);

        $this->processCard();
    }

    /**
     * Handle everything
     *
     * Render the actual Card, including dispatching the Render Event. Set the options for the Section render, attach
     * a listener to the SectionEvent. This is called from the constructor and cannot be accessed publicly.
     *
     * @return void
     */
    private function processCard()
    {
        $this->renderCard();
        $this->addListener();
    }

    private function renderCard()
    {
        $dispatchResult = $this->ed->dispatch(RenderEvent::EVENT_HANDLE, new RenderEvent(self::CARD_ID));
    }

    private function setOpts()
    {
        global $GLOBALS;
        global $pid;
        $this->opts = [
            'acl' => ['patients', 'dem'],
            'initiallyCollapsed' => (getUserSetting(self::CARD_ID) == 0) ? false : true,
            'add' => false,
            'edit' => false,
            'collapse' => true,
            'templateFile' => self::TEMPLATE_FILE,
            'identifier' => self::CARD_ID,
            'title' => xl('Renal Failure Medication Alert'),
            'templateVariables' => [
                'summary' => 'Potential Medication Issue : IBUPROFEN',
                'detail' => 'Current research suggests IBUPROFEN should be limited for patients with renal issues.',
                'indicator' => '',
                'source' => [
                    'label' => '',
                    'url' => '',
                    'icon' => ''
                ],
                'links' => [
                    'label' => 'More Info',
                    'url' => 'https://renaldrugdatabase.com/monographs/ibuprofen',
                    'type' => '',
                    'appcontext' => ''
                ]
            ],
        ];
    }

    private function getOpts()
    {
        return $this->opts;
    }

    private function addListener()
    {
        $this->ed->addListener(SectionEvent::EVENT_HANDLE, [$this, 'addPatientCardToSection']);
    }

    public function addPatientCardToSection(SectionEvent $e)
    {
        if ($e->getSection('secondary')) {
            $card = new CardModel($this->getOpts());
            $e->addCard($card);
        }
        return $e;
    }
}

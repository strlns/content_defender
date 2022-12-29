<?php

declare(strict_types=1);

namespace IchHabRecht\ContentDefender\Tests\Functional;

/*
 * This file is part of the TYPO3 extension content_defender.
 *
 * (c) Nicole Cordes <typo3@cordes.co>
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'fluid_styled_content',
    ];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/content_defender',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $fixturePath = ORIGINAL_ROOT . 'typo3conf/ext/content_defender/Tests/Functional/Fixtures/Database/';
        $this->importCSVDataSet($fixturePath . 'be_users.csv');
        $this->importCSVDataSet($fixturePath . 'sys_language.csv');
        $this->importCSVDataSet($fixturePath . 'pages.csv');
        $this->importCSVDataSet($fixturePath . 'tt_content.csv');

        if (!empty($GLOBALS['TCA']['pages_language_overlay'])) {
            $this->importCSVDataSet($fixturePath . 'pages_language_overlay.csv');
        }

        ExtensionManagementUtility::addPageTSConfig(
            '<INCLUDE_TYPOSCRIPT: source="DIR:EXT:content_defender/Tests/Functional/Fixtures/TSconfig/BackendLayouts" extensions="ts">'
        );

        $this->setUpBackendUser(1);
        Bootstrap::initializeLanguageObject();
    }

    protected function assertNoProcessingErrorsInDataHandler(DataHandler $dataHandler)
    {
        $dataHandler->printLogErrorMessages();
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();

        $this->assertSame(0, count($flashMessageQueue->getAllMessages()));
    }

    protected function getQueryBuilderForTable(string $table)
    {
        $queryBuilder  = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }

    /**
     * @param array $input
     * @param array $defaultValues
     * @return array
     */
    protected function mergeDefaultValuesWithCompilerInput(array $input, array $defaultValues)
    {
        if (version_compare(TYPO3_version, '10', '>=')) {
            $input = array_merge($input, ['defaultValues' => $defaultValues]);
        } else {
            // TODO: 9.5 legacy support
            if (!isset($_GET['defVals'])) {
                $_GET['defVals'] = [];
            }
            $_GET['defVals'] = array_merge($_GET['defVals'], $defaultValues);
        }

        return $input;
    }

    protected function setUpFrontendRootPage($pageId, array $typoScriptFiles = [], array $templateValues = [])
    {
        parent::setUpFrontendRootPage($pageId, $typoScriptFiles, $templateValues);

        $path = Environment::getConfigPath() . '/sites/' . $pageId . '/';
        $target = $path . 'config.yaml';
        $file = ORIGINAL_ROOT . 'typo3conf/ext/content_defender/Tests/Functional/Fixtures/Frontend/site.yaml';
        if (!file_exists($target)) {
            GeneralUtility::mkdir_deep($path);
            $fileContent = file_get_contents($file);
            $fileContent = str_replace('\'{rootPageId}\'', (string)$pageId, $fileContent);
            GeneralUtility::writeFile($target, $fileContent);
        }
    }
}

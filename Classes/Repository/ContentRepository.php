<?php

declare(strict_types=1);

namespace IchHabRecht\ContentDefender\Repository;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\BackendWorkspaceRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

class ContentRepository
{
    protected ColPosCountState $colPosCount;

    public function __construct(ColPosCountState $colPosCount = null)
    {
        $this->colPosCount = $colPosCount ?? GeneralUtility::makeInstance(ColPosCountState::class);
    }

    public function countColPosByRecord(array $record): int
    {
        $identifier = $this->getIdentifier($record);

        if (!isset($this->colPosCount[$identifier])) {
            $this->initialize($record);
        }

        return count($this->colPosCount[$identifier]);
    }

    public function addRecordToColPos(array $record): int
    {
        $identifier = $this->getIdentifier($record);

        if (!isset($this->colPosCount[$identifier])) {
            $this->initialize($record);
        }

        $uid = ($record['t3ver_oid'] ?? 0) ?: $record['uid'];
        $this->colPosCount[$identifier][$uid] = $uid;

        return count($this->colPosCount[$identifier]);
    }

    public function removeRecordFromColPos(array $record): int
    {
        $identifier = $this->getIdentifier($record);

        if (!isset($this->colPosCount[$identifier])) {
            $this->initialize($record);
        }

        $uid = ($record['t3ver_oid'] ?? 0) ?: $record['uid'];
        if (isset($this->colPosCount[$identifier][$uid])) {
            unset($this->colPosCount[$identifier][$uid]);
        }

        return count($this->colPosCount[$identifier]);
    }

    public function isRecordInColPos(array $record): bool
    {
        $identifier = $this->getIdentifier($record);

        if (!isset($this->colPosCount[$identifier])) {
            $this->initialize($record);
        }

        $uid = ($record['t3ver_oid'] ?? 0) ?: $record['uid'];

        return isset($this->colPosCount[$identifier][$uid]);
    }

    public function substituteNewIdsWithUids(array $newIdUidArray)
    {
        if (empty($newIdUidArray)) {
            return;
        }

        foreach ($this->colPosCount as $identifier => $uidArray) {
            $intersect = array_intersect_key($newIdUidArray, $uidArray);
            if (empty($intersect)) {
                continue;
            }
            $this->colPosCount[$identifier] = array_replace(
                array_diff_key($uidArray, $newIdUidArray),
                array_combine($intersect, $intersect)
            );
        }
    }

    protected function initialize(array $record)
    {
        $this->colPosCount[$this->getIdentifier($record)] = $this->fetchRecordsForColPos($record);
    }

    protected function fetchRecordsForColPos(array $record): array
    {
        $rows = [];

        $languageField = $GLOBALS['TCA']['tt_content']['ctrl']['languageField'];
        $language = (array)$record[$languageField];

        $selectFields = ['uid', 'pid'];
        if (!empty($GLOBALS['TCA']['tt_content']['ctrl']['versioningWS'])) {
            $selectFields[] = 't3ver_state';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(BackendWorkspaceRestriction::class));

        $statement = $queryBuilder->select(...$selectFields)
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($record['pid'], \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'colPos',
                    $queryBuilder->createNamedParameter($record['colPos'], \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $languageField,
                    $queryBuilder->createNamedParameter($language[0], \PDO::PARAM_INT)
                )
            )
            ->execute();

        while ($row = $statement->fetch()) {
            BackendUtility::workspaceOL('tt_content', $row, -99, true);
            if (is_array($row) && !VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                $rows[$row['uid']] = $row['uid'];
            }
        }

        return $rows;
    }

    protected function getIdentifier(array $record): string
    {
        $languageField = $GLOBALS['TCA']['tt_content']['ctrl']['languageField'];
        $language = (array)$record[$languageField];

        return implode('_', [$record['pid'], $language[0], $record['colPos']]);
    }
}

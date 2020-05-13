<?php
declare(strict_types=1);

namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Rename elasticsearch fields to beats convention
 */
class Version20200513223401 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getIdentifier()
    {
        return 'Flowpack.ElasticSearch.ContentRepositoryAdaptor-20200513223401';
    }

    /**
     * @return void
     */
    public function up()
    {
        $fieldMapping = [
            '__identifier' => 'neos_node_identifier',
            '__parentPath' => 'neos_parent_path ',
            '__path' => 'neos_path',
            '__typeAndSupertypes' => 'neos_type_and_supertypes',
            '__workspace' => 'neos_workspace',
            '_creationDateTime' => 'neos_creation_date_time',
            '_hidden' => 'neos_hidden',
            '_hiddenBeforeDateTime' => 'neos_hidden_before_datetime',
            '_hiddenAfterDateTime' => 'neos_hidden_after_datetime',
            '_hiddenInIndex' => 'neos_hidden_in_index',
            '_lastModificationDateTime' => 'neos_last_modification_date_time',
            '_lastPublicationDateTime' => 'neos_last_publication_date_time',
            '__fulltextParts' => 'neos_fulltext_parts',
            '__fulltext' => 'neos_fulltext',
        ];

        foreach ($fieldMapping as $search => $replace) {
            $this->searchAndReplace($search, $replace, ['php', 'yaml', 'fusion']);
        }
    }
}

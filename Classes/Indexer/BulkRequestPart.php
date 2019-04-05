<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer;

class BulkRequestPart
{
    /**
     * @var array
     */
    protected $targetDimensionsHash;

    /**
     * @var array
     */
    protected $items;

    public function __construct(string $targetDimensionsHash, array $items)
    {
        $this->targetDimensions = $targetDimensionsHash;
        $this->items = $items;
    }

    /**
     * @return array
     */
    public function getTargetDimensionsHash(): array
    {
        return $this->targetDimensionsHash;
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }
}

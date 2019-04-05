<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer;

class BulkRequestPart
{
    /**
     * @var array
     */
    protected $targetDimensions;

    /**
     * @var array
     */
    protected $items;

    public function __construct(array $targetDimensions, array $items)
    {
        $this->targetDimensions = $targetDimensions;
        $this->items = $items;
    }

    /**
     * @return array
     */
    public function getTargetDimensions(): array
    {
        return $this->targetDimensions;
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }
}

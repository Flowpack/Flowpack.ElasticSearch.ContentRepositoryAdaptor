<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Dto;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class AssetContent
{

    /**
     * @var string
     */
    protected $content;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $author;
    /**
     * @var string
     */
    protected $keywords;
    /**
     * @var string
     */
    protected $date;
    /**
     * @var string
     */
    protected $contentType;
    /**
     * @var int
     */
    protected $contentLength;
    /**
     * @var string
     */
    protected $language;

    /**
     * AssetContent constructor.
     * @param string $content
     * @param string $title
     * @param string $name
     * @param string $author
     * @param string $keywords
     * @param string $date
     * @param string $contentType
     * @param int $contentLength
     * @param string $language
     */
    public function __construct(string $content, string $title, string $name, string $author, string $keywords, string $date, string $contentType, int $contentLength, string $language)
    {
        $this->content = $content;
        $this->title = $title;
        $this->name = $name;
        $this->author = $author;
        $this->keywords = $keywords;
        $this->date = $date;
        $this->contentType = $contentType;
        $this->contentLength = $contentLength;
        $this->language = $language;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * @return string
     */
    public function getKeywords(): string
    {
        return $this->keywords;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return int
     */
    public function getContentLength(): int
    {
        return $this->contentLength;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }
}

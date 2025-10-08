<?php

namespace Prism\Backend\Models;

class Tab
{
    private string $id;
    private string $title;
    private string $url;
    private bool $isActive;
    private \DateTime $createdAt;

    public function __construct(string $id, string $title, string $url, bool $isActive = false)
    {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt->format('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'is_active' => $this->isActive,
            'created_at' => $this->getCreatedAt()
        ];
    }
}

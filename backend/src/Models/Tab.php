<?php

namespace Prism\Backend\Models;

class Tab
{
    private string $id;
    private string $title;
    private string $url;
    private bool $isActive;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;
    private ?string $userId;

    public function __construct(string $id, string $title, string $url, bool $isActive = false, ?string $userId = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        $this->isActive = $isActive;
        $this->userId = $userId;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public static function fromArray(array $data): Tab
    {
        $tab = new Tab(
            $data['id'],
            $data['title'],
            $data['url'],
            (bool)($data['is_active'] ?? false),
            $data['user_id'] ?? null
        );
        
        if (isset($data['created_at'])) {
            $tab->createdAt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $tab->updatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['updated_at']);
        }
        
        return $tab;
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
        $this->updatedAt = new \DateTime();
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
        $this->updatedAt = new \DateTime();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt->format('Y-m-d H:i:s');
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
        $this->updatedAt = new \DateTime();
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
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'user_id' => $this->userId
        ];
    }
}

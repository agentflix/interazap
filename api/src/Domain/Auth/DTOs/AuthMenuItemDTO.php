<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO for menu item with permissions and children.
 *
 * @readonly
 */
final readonly class AuthMenuItemDTO
{
    /**
     * @param  array<int, AuthMenuItemDTO>  $children
     */
    public function __construct(
        public string $label,
        public string $route,
        public ?string $icon = null,
        public ?string $permission = null,
        public array $children = [],
    ) {}

    /**
     * @param  array<int, AuthMenuItemDTO>  $children
     */
    public function withChildren(array $children): self
    {
        return new self(
            label: $this->label,
            route: $this->route,
            icon: $this->icon,
            permission: $this->permission,
            children: $children,
        );
    }

    /**
     * @return array{label:string,route:string,icon:?string,permission:?string,children:array<int, array<string,mixed>>}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'route' => $this->route,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'children' => array_map(static fn (AuthMenuItemDTO $item): array => $item->toArray(), $this->children),
        ];
    }
}

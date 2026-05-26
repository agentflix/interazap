<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO para item de menu com permissão e filhos.
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
     * Retorna nova instância com os filhos substituídos.
     *
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
     * Serializa o item de menu e seus filhos recursivamente para array.
     *
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

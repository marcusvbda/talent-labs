<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\PlanKey;
use App\Enums\PlanSource;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\RegionResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->string()
                    ->maxLength(255),

                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),

                Toggle::make('is_admin')
                    ->label('Admin')
                    ->default(false),

                Select::make('status')
                    ->options(UserStatus::class)
                    ->default(UserStatus::Active)
                    ->required(),

                Select::make('plan_key')
                    ->options(PlanKey::class)
                    ->default(PlanKey::Free)
                    ->helperText('Changing the plan here makes it manual. An active Stripe subscription keeps billing until it is cancelled in Stripe.')
                    ->required(),

                Select::make('plan_source')
                    ->label('Billing')
                    ->options(PlanSource::class)
                    ->disabled()
                    ->dehydrated(false),

                Select::make('country')
                    ->options(function (): array {
                        /** @var array<string, array{currency: string, countries: list<string>}> $regions */
                        $regions = config('talent.regions');

                        $countries = collect($regions)
                            ->flatMap(fn (array $region): array => $region['countries'])
                            ->unique()
                            ->sort()
                            ->values();

                        return $countries->combine($countries)->all();
                    })
                    ->searchable()
                    ->placeholder('Select a country')
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('region', RegionResolver::fromCountry($state)->getLabel());
                    }),

                TextInput::make('region')
                    ->label('Region (derived)')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?string $state, ?User $record): ?string => filled($state)
                        ? $state
                        : $record?->region?->getLabel()),

                Select::make('locale')
                    ->options(function (): array {
                        /** @var list<string> $locales */
                        $locales = config('talent.app_locales');

                        return collect($locales)->mapWithKeys(fn (string $locale): array => [$locale => strtoupper($locale)])->all();
                    })
                    ->required(),
            ]);
    }
}

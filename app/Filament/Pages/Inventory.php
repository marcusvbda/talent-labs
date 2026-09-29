<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Reports\InventoryFunnel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * @property-read Schema $form
 *
 * @phpstan-import-type Funnel from InventoryFunnel
 */
class Inventory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'inventory';

    protected static ?string $title = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory';

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var Funnel|array{} */
    public array $report = [];

    public function mount(): void
    {
        $this->form->fill(['days' => 30, 'client' => null]);
        $this->compute(30, null);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('days')
                    ->label('Days')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(365),
                Select::make('client')
                    ->label('Client')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where('is_admin', false)
                        ->where('name', 'ilike', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} (#{$user->id})"])
                        ->all())
                    ->getOptionLabelUsing(function (mixed $value): ?string {
                        $user = User::query()->where('is_admin', false)->whereKey($value)->first();

                        return $user === null ? null : "{$user->name} (#{$user->id})";
                    }),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Options')
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('form')
                            ->livewireSubmitHandler('refresh'),
                    ]),
                View::make('filament.inventory.summary')->viewData(fn (): array => ['report' => $this->report]),
                Section::make('Per source')
                    ->schema([EmbeddedTable::make()]),
                View::make('filament.inventory.stock')->viewData(fn (): array => ['report' => $this->report]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->sourceRecords())
            ->columns([
                TextColumn::make('name')->label('Source'),
                TextColumn::make('active')->label('Active'),
                TextColumn::make('fetched')->label('Fetched'),
                TextColumn::make('new')->label('New'),
                TextColumn::make('postings')->label('Postings'),
                TextColumn::make('target')->label('Target'),
                TextColumn::make('company')->label('Company'),
                TextColumn::make('domain')->label('Domain'),
                TextColumn::make('verified')->label('Verified'),
                TextColumn::make('profile_done')->label('Profile done'),
                TextColumn::make('en')->label('EN'),
                TextColumn::make('pt')->label('PT'),
                TextColumn::make('other')->label('Other'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No sources');
    }

    public function refresh(): void
    {
        $state = $this->form->getState();
        $clientId = $state['client'] ?? null;

        $this->compute(
            (int) $state['days'],
            $clientId === null || $clientId === '' ? null : User::query()->where('is_admin', false)->whereKey($clientId)->first(),
        );
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->refresh()),
        ];
    }

    private function compute(int $days, ?User $user): void
    {
        $this->report = app(InventoryFunnel::class)->build($days, $user);
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function sourceRecords(): array
    {
        if ($this->report === []) {
            return [];
        }

        $records = [];

        foreach ($this->report['sources'] as $source) {
            $records['source-'.$source['id']] = $this->row($source['name'], $source['is_active'] ? 'yes' : 'no', $source);
        }

        $records['total'] = $this->row('Total', '', $this->report['totals']);

        return $records;
    }

    /**
     * @param  array{fetched: int, new: int, postings: int, target_family: int, with_company: int, with_domain: int, verified: int, profile_done: int, lang: array{en: int, pt: int, other: int}}  $counts
     * @return array<string, int|string>
     */
    private function row(string $name, string $active, array $counts): array
    {
        $postings = $counts['postings'];
        $withPercent = fn (int $count): string => $count.' ('.($postings > 0 ? round($count / $postings * 100) : 0).'%)';

        return [
            'name' => $name,
            'active' => $active,
            'fetched' => $counts['fetched'],
            'new' => $counts['new'],
            'postings' => $postings,
            'target' => $withPercent($counts['target_family']),
            'company' => $withPercent($counts['with_company']),
            'domain' => $withPercent($counts['with_domain']),
            'verified' => $withPercent($counts['verified']),
            'profile_done' => $withPercent($counts['profile_done']),
            'en' => $counts['lang']['en'],
            'pt' => $counts['lang']['pt'],
            'other' => $counts['lang']['other'],
        ];
    }
}

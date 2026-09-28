<?php

namespace App\Filament\Pages;

use App\Filament\Actions\CollectJobsNowAction;
use App\Models\CollectionSchedule;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class CollectionSchedulePage extends Page
{
    protected static ?string $slug = 'collection-schedule';

    protected static ?string $title = 'Collection schedule';

    protected static ?string $navigationLabel = 'Collection schedule';

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    private function fillForm(): void
    {
        CollectionSchedule::forgetCurrent();
        $schedule = CollectionSchedule::current();

        $this->form->fill([
            'enabled' => $schedule->enabled,
            'times' => $schedule->times,
            'timezone' => $schedule->timezone,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $timezones = DateTimeZone::listIdentifiers();

        return $schema
            ->components([
                Toggle::make('enabled')
                    ->label('Automatic collection'),
                TagsInput::make('times')
                    ->label('Times (HH:MM, 24 h)')
                    ->required()
                    ->rules(['array', 'min:1', 'max:12'])
                    ->nestedRecursiveRules(['regex:/^([01]\d|2[0-3]):[0-5]\d$/', 'distinct'])
                    ->helperText('Runs per day = number of times. A run already in progress makes the next slot skip.'),
                Select::make('timezone')
                    ->searchable()
                    ->required()
                    ->options(array_combine($timezones, $timezones))
                    ->rules([Rule::in($timezones)]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.collection-schedule.realtime-listener'),
                Section::make('Status')
                    ->schema([
                        View::make('filament.collection-schedule.status'),
                    ]),
                Section::make('Schedule')
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('form')
                            ->livewireSubmitHandler('save')
                            ->footer([
                                Actions::make([
                                    Action::make('save')
                                        ->label('Save')
                                        ->submit('save')
                                        ->keyBindings(['mod+s']),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        /** @var list<string> $times */
        $times = array_values(array_unique(array_map('strval', $state['times'])));
        sort($times);

        $schedule = CollectionSchedule::current();
        $schedule->forceFill([
            'enabled' => (bool) $state['enabled'],
            'times' => $times,
            'timezone' => $state['timezone'],
            'updated_by' => auth()->id(),
        ])->save();

        $this->fillForm();

        Notification::make()
            ->title('Schedule saved')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pause')
                ->label('Pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Automatic runs stop until you resume. Run now still works.')
                ->visible(fn (): bool => CollectionSchedule::current()->enabled)
                ->action(function (): void {
                    $this->setEnabled(false);

                    Notification::make()
                        ->title('Automatic collection paused')
                        ->success()
                        ->send();
                }),
            Action::make('resume')
                ->label('Resume')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! CollectionSchedule::current()->enabled)
                ->action(function (): void {
                    $this->setEnabled(true);

                    Notification::make()
                        ->title('Automatic collection resumed')
                        ->success()
                        ->send();
                }),
            CollectJobsNowAction::make('runNow')->label('Run now'),
        ];
    }

    private function setEnabled(bool $enabled): void
    {
        CollectionSchedule::current()->forceFill([
            'enabled' => $enabled,
            'updated_by' => auth()->id(),
        ])->save();

        $this->data['enabled'] = $enabled;
    }
}

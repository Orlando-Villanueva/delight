<?php

namespace App\Filament\Resources\Announcements\RelationManagers;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\AnnouncementEmailDelivery;
use App\Services\AnnouncementDeliveryStatusService;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class EmailDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'emailDeliveries';

    protected static ?string $title = 'Recipient records';

    public function mount(): void
    {
        abort_unless(AnnouncementResource::canViewAny(), 403);
        parent::mount();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AnnouncementResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On('announcement-deliveries-retried')]
    public function refreshAfterRetry(int $announcementId): void
    {
        if ($announcementId !== $this->getOwnerRecord()->id) {
            return;
        }

        $this->flushCachedTableRecords();
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function infolist(Schema $schema): Schema
    {
        $timestamps = [];

        foreach ([
            'sending_at' => 'Attempt started at',
            'next_attempt_at' => 'Next retry recorded for',
            'sent_at' => 'Transport-submitted at',
            'skipped_at' => 'Skipped at',
            'failed_at' => 'Failed at',
            'uncertain_at' => 'Uncertain at',
        ] as $field => $label) {
            $timestamps[] = TextEntry::make($field)->label($label)->dateTime('M j, Y H:i:s')->placeholder('Not recorded');
        }

        return $schema->columns(2)->components([
            TextEntry::make('recipient_email')->label('Recorded recipient')->wrap()->columnSpanFull(),
            TextEntry::make('outcome')->state(fn (AnnouncementEmailDelivery $record, AnnouncementDeliveryStatusService $service): string => $service->recipientStatus($record)),
            TextEntry::make('attempt_count')->label('Attempts'),
            TextEntry::make('failure_reason')->label('Recorded reason')->placeholder('Not recorded')
                ->wrap()
                ->helperText('A reason may remain from an earlier attempt or an opt-out. The outcome comes from recorded timestamps.')
                ->columnSpanFull(),
            ...$timestamps,
            TextEntry::make('message_id')->label('Message identifier')->placeholder('Not recorded')->wrap()->columnSpanFull(),
            TextEntry::make('provider_message_id')->label('Provider message identifier')->placeholder('Not recorded')->wrap()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('recipient_email')
            ->description('These are recorded processing outcomes, not confirmed inbox delivery.')
            ->columns([
                Split::make([
                    TextColumn::make('recipient_email')->label('Recipient')->searchable()->wrap(),
                    TextColumn::make('outcome')
                        ->state(fn (AnnouncementEmailDelivery $record, AnnouncementDeliveryStatusService $service): string => $service->recipientStatus($record))
                        ->badge()->color(fn (string $state): string => match ($state) {
                            'Failed' => 'danger', 'Uncertain' => 'warning', 'Transport-submitted' => 'success',
                            'Pending' => 'info', default => 'gray',
                        }),
                    TextColumn::make('attempt_count')->label('Attempts')->prefix('Attempts: '),
                ])->from('md'),
            ])
            ->deferFilters(false)
            ->filters([
                SelectFilter::make('outcome')->options(AnnouncementDeliveryStatusService::RECIPIENT_OUTCOMES)
                    ->query(fn (Builder $query, array $data, AnnouncementDeliveryStatusService $service): Builder => $service->filterRecipients($query, $data['value'] ?? null)),
            ])
            ->recordActions([ViewAction::make()->label('Details')->modalHeading('Recipient processing details')])
            ->defaultSort('id', 'desc')->paginationPageOptions([20, 50, 100])->defaultPaginationPageOption(20);
    }
}

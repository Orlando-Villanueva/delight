<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\EmailDeliveriesRelationManager;
use App\Models\Announcement;
use App\Services\AnnouncementDeliveryStatusService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $slug = 'manage-announcements';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    /** @return Builder<Announcement> */
    public static function getEloquentQuery(): Builder
    {
        return app(AnnouncementDeliveryStatusService::class)->withDeliveryCounts(parent::getEloquentQuery());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function infolist(Schema $schema): Schema
    {
        $counts = [];

        foreach (['total' => 'Recorded recipients', 'pending' => 'Pending', 'submitted' => 'Transport-submitted', 'skipped' => 'Skipped', 'failed' => 'Failed', 'uncertain' => 'Uncertain'] as $key => $label) {
            $counts[] = TextEntry::make('delivery_'.$key)->label($label)
                ->state(fn (Announcement $record, AnnouncementDeliveryStatusService $service): int => $service->summarize($record)[$key]);
        }

        return $schema->components([
            Section::make('Publication')->columns(2)->columnSpanFull()->schema([
                TextEntry::make('title')->columnSpanFull(),
                TextEntry::make('slug'),
                TextEntry::make('publication_status')->label('Publication status')
                    ->state(fn (Announcement $record): string => self::publicationStatus($record)),
                TextEntry::make('starts_at')->label('Publication time')->dateTime('M j, Y H:i:s')->placeholder('Not recorded'),
                TextEntry::make('ends_at')->label('Expiry')->dateTime('M j, Y H:i:s')->placeholder('No expiry'),
            ]),
            Section::make('Email processing')
                ->description('Authorization permits asynchronous processing. Recorded submission and completion do not confirm inbox delivery.')
                ->columns(2)->columnSpanFull()->schema([
                    TextEntry::make('delivery_status')->label('Recorded status')
                        ->state(fn (Announcement $record, AnnouncementDeliveryStatusService $service): string => $service->summarize($record)['status'])
                        ->columnSpanFull(),
                    TextEntry::make('email_broadcast_authorized_at')->label('Email authorized at')->dateTime('M j, Y H:i:s')->placeholder('Not authorized'),
                    TextEntry::make('email_audience_finalized_at')->label('Audience finalized at')->dateTime('M j, Y H:i:s')->placeholder('Not finalized'),
                    TextEntry::make('email_broadcast_completed_at')->label('Processing completed at')->dateTime('M j, Y H:i:s')->placeholder('Not recorded'),
                    TextEntry::make('sent_via_email_at')->label('Legacy email timestamp')->dateTime('M j, Y H:i:s')->placeholder('Not recorded'),
                ]),
            Section::make('Recorded recipient outcomes')->columns(['sm' => 2, 'lg' => 3])->columnSpanFull()->schema($counts),
        ]);
    }

    public static function getRelations(): array
    {
        return [EmailDeliveriesRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Email authorization is separate from publication. Transport submission does not confirm inbox delivery.')
            ->columns([
                Split::make([
                    TextColumn::make('title')
                        ->description(fn (Announcement $record): string => '/'.$record->slug)
                        ->searchable(['title', 'slug'])
                        ->wrap(),
                    Stack::make([
                        TextColumn::make('publication_status')
                            ->state(fn (Announcement $record): string => self::publicationStatus($record))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Published' => 'success',
                                'Scheduled' => 'info',
                                default => 'gray',
                            }),
                        TextColumn::make('starts_at')
                            ->label('Publication date')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('No publication date')
                            ->description('Publication date'),
                    ])->space(1),
                    Stack::make([
                        TextColumn::make('delivery_status')
                            ->state(fn (Announcement $record, AnnouncementDeliveryStatusService $service): string => $service->summarize($record)['status'])
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Needs attention' => 'danger',
                                'Uncertain', 'Audience not finalized', 'Awaiting completion' => 'warning',
                                'Scheduled', 'Pending recipients' => 'info',
                                'Processing completed' => 'success',
                                default => 'gray',
                            }),
                        TextColumn::make('delivery_summary')
                            ->state(fn (Announcement $record, AnnouncementDeliveryStatusService $service): string => self::deliveryCounts($service->summarize($record)))
                            ->wrap(),
                        TextColumn::make('email_broadcast_authorized_at')
                            ->formatStateUsing(fn (): string => 'Email authorized')
                            ->placeholder('Email not authorized')
                            ->hidden(fn (?Announcement $record, AnnouncementDeliveryStatusService $service): bool => $record !== null && $service->summarize($record)['status'] === 'Not authorized'),
                    ])->space(1),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('publication_status')
                    ->label('Publication status')
                    ->options(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'expired' => 'Expired'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'draft' => $query->where('is_draft', true),
                            'scheduled' => $query->where('is_draft', false)->where('starts_at', '>', now()),
                            'published' => $query->visible(),
                            'expired' => $query->published()->where('ends_at', '<', now()),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('details')->label('Details')
                    ->url(fn (Announcement $record): string => static::getUrl('view', ['record' => $record], panel: 'admin')),
                Action::make('editDraft')
                    ->label('Edit draft')
                    ->url(fn (Announcement $record): string => route('admin.announcements.edit', $record))
                    ->visible(fn (Announcement $record): bool => $record->is_draft),
                Action::make('view')
                    ->label(fn (Announcement $record): string => $record->isPublished() ? 'View' : 'Preview')
                    ->url(fn (Announcement $record): string => $record->isPublished()
                        ? route('announcements.show', $record->slug)
                        : route('admin.announcements.preview', $record->slug)),
            ])
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([20, 50, 100])
            ->defaultPaginationPageOption(20)
            ->poll(fn (ListAnnouncements $livewire, AnnouncementDeliveryStatusService $service): ?string => $livewire->getTableRecords()
                ->contains(fn (Announcement $record): bool => $service->summarize($record)['active']) ? '15s' : null);
    }

    private static function publicationStatus(Announcement $record): string
    {
        return match (true) {
            $record->is_draft => 'Draft',
            $record->starts_at?->isFuture() === true => 'Scheduled',
            $record->ends_at?->lt(now()->startOfSecond()) === true => 'Expired',
            default => 'Published',
        };
    }

    /** @param array{audience_finalized: bool, completed: bool, total: int, pending: int, submitted: int, skipped: int, failed: int, uncertain: int, handled: int} $summary */
    private static function deliveryCounts(array $summary): string
    {
        if (! $summary['audience_finalized'] && $summary['total'] === 0 && ! $summary['completed']) {
            return 'No recipient audience recorded';
        }

        $parts = [$summary['handled'].' of '.$summary['total'].' handled'];

        if ($summary['completed']) {
            array_unshift($parts, 'Completed processing');
        }

        foreach (['submitted' => 'transport-submitted', 'pending' => 'pending', 'skipped' => 'skipped', 'failed' => 'failed', 'uncertain' => 'uncertain'] as $key => $label) {
            if ($summary[$key] > 0) {
                $parts[] = $summary[$key].' '.$label;
            }
        }

        return implode(' · ', $parts);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'view' => ViewAnnouncement::route('/{record}'),
        ];
    }
}

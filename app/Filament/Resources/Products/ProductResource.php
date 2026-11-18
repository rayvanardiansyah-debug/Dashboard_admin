<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Manajemen Master';

    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('category_id')
                    ->label('Kategori Produk')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Kode Produk')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('barcode')
                    ->label('Barcode')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                TextInput::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
                // Disk "public" agar sama dengan CRUD lama (asset('storage/...')).
                FileUpload::make('image')
                    ->label('Gambar Produk')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('products'),
            ]);
    }

    // Acara 33, Langkah 6 + Acara 34, Langkah 2: kolom Table, badge, dan filter
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Gambar')
                    ->disk('public'),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->badge()
                    ->color(fn (int $state): string => $state <= 5 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Filter Kategori')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}

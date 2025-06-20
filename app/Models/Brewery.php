<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brewery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'city',
        'address',
        'latitude',
        'longitude',
        'founded_year',
        'website',
        'visitable',
        'image'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'founded_year' => 'integer',
        'visitable' => 'boolean'
    ];

    /**
     * Retorna la imagen o una imagen predeterminada si no hay imagen asignada
     */
    public function getImageAttribute($value)
    {
        return $value ? $value : 'breweries/default.jpg';
    }

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Relación muchos a muchos con cervezas
     */
    public function beers()
    {
        return $this->belongsToMany(Beer::class, 'brewery_beer');
    }
    
    /**
     * Obtener los usuarios que han marcado esta cervecería como favorita.
     */
    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'brewery_favorites');
    }
    
    /**
     * Obtener todas las reseñas de esta cervecería
     */
    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
    
    /**
     * Calcular la calificación promedio
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?: 0;
    }
    
    /**
     * Scope para filtrar cervecerías
     */
    public function scopeFilter($query, $filters)
    {
        // Filtrar por nombre
        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        
        // Filtrar por ciudad
        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }
        
        // Filtrar por visitables
        if (isset($filters['visitable'])) {
            $query->where('visitable', $filters['visitable']);
        }
        
        // Filtrar por año de fundación
        if (!empty($filters['year_min']) && !empty($filters['year_max'])) {
            $query->whereBetween('founded_year', [$filters['year_min'], $filters['year_max']]);
        } elseif (!empty($filters['year_min'])) {
            $query->where('founded_year', '>=', $filters['year_min']);
        } elseif (!empty($filters['year_max'])) {
            $query->where('founded_year', '<=', $filters['year_max']);
        }
        
        // Filtrar por usuario
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        // Ordenar resultados
        $orderBy = $filters['order_by'] ?? 'name';
        $orderDirection = $filters['order_direction'] ?? 'asc';
        
        if ($orderBy === 'rating') {
            $query->withAvg('reviews', 'rating')
                  ->orderBy('reviews_avg_rating', $orderDirection);
        } else {
            $allowedFields = ['name', 'city', 'founded_year'];
            if (in_array($orderBy, $allowedFields)) {
                $query->orderBy($orderBy, $orderDirection);
            } else {
                $query->orderBy('name', 'asc');
            }
        }
        
        return $query;
    }
    
    /**
     * Get the route key name for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'name'; // Indicamos que usamos la columna 'name'
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKey()
    {
        // Limpia el nombre para URL
        return \Str::slug($this->name);
    }
}

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('breweries', function (Blueprint $table) {
            if (!Schema::hasColumn('breweries', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('breweries', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('breweries', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('breweries', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('breweries', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('breweries', 'founded_year')) {
                $table->integer('founded_year')->nullable();
            }
            if (!Schema::hasColumn('breweries', 'website')) {
                $table->string('website')->nullable();
            }
            if (!Schema::hasColumn('breweries', 'visitable')) {
                $table->boolean('visitable')->default(false);
            }
            if (!Schema::hasColumn('breweries', 'image')) {
                $table->string('image')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('breweries', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'address', 'city', 'founded_year', 'website', 'visitable', 'image']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

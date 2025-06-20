<?php

namespace App\Http\Controllers;

use App\Models\Brewery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BreweryController extends Controller
{
    /**
     * Mostrar lista de cervecerías
     */
    public function index(Request $request)
    {
        $query = Brewery::with('beers');
        
        // Filtrar por ubicación (usando nombres)
        if ($request->has('location')) {
            $query->where('city', 'like', '%' . $request->location . '%')
                  ->orWhere('location', 'like', '%' . $request->location . '%');
        }
        
        // Búsqueda por nombre
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }
        
        $breweries = $query->latest()->paginate(12);
        
        return view('breweries.index', compact('breweries'));
    }

    /**
     * Mostrar una cervecería específica
     */
    public function show($breweryParam)
    {
        // Intentar encontrar por ID (para mantener compatibilidad)
        if (is_numeric($breweryParam)) {
            $brewery = Brewery::find($breweryParam);
            if ($brewery) {
                return view('breweries.show', compact('brewery', 'beers'));
            }
        }
        
        // Intentar encontrar por nombre exacto
        $brewery = Brewery::where('name', $breweryParam)->first();
        if (!$brewery) {
            // Intentar encontrar por slug
            $brewery = Brewery::all()->first(function($b) use ($breweryParam) {
                return \Str::slug($b->name) === $breweryParam;
            });
        }
        
        if (!$brewery) {
            abort(404, 'Cervecería no encontrada');
        }
        
        $beers = $brewery->beers()->paginate(8);
        return view('breweries.show', compact('brewery', 'beers'));
    }

    /**
     * Mostrar las cervecerías del usuario actual (solo empresas)
     */
    public function myBreweries()
    {
        $breweries = Auth::user()->breweries()->with('beers')->latest()->paginate(10);
        return view('breweries.my_breweries', compact('breweries'));
    }

    /**
     * Mostrar formulario para crear cervecería
     */
    public function create()
    {
        // Añade un log para verificar que se está ejecutando
        \Log::info('Método create() del BreweryController llamado');
        
        return view('breweries.create');
    }

    /**
     * Almacenar nueva cervecería
     */
    public function store(Request $request)
    {
        // Añade validación para nombre único
        $request->validate([
            'name' => 'required|string|max:255|unique:breweries,name',
            'description' => 'required|string',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'founded_year' => 'nullable|integer|min:1800|max:' . date('Y'),
            'website' => 'nullable|url|max:255',
            'visitable' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        // Manejar la carga de imagen
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('breweries/uploads', 'public');
        }

        // Asignar el usuario actual como propietario
        $validated['user_id'] = Auth::id();

        $brewery = new Brewery();
        $brewery->name = $request->name;
        
        // Generar un slug único
        $baseSlug = Str::slug($request->name);
        $slug = $baseSlug;
        $counter = 1;
        
        // Si el slug ya existe, añadir un sufijo numérico
        while (Brewery::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $brewery->slug = $slug;

        // Crear la cervecería
        $brewery = Brewery::create($validated);

        return redirect()->route('my_breweries')
            ->with('success', 'Cervecería creada exitosamente');
    }

    /**
     * Mostrar formulario para editar cervecería
     */
    public function edit($breweryParam)
    {
        // Intentar encontrar por ID (para mantener compatibilidad)
        if (is_numeric($breweryParam)) {
            $brewery = Brewery::find($breweryParam);
            if ($brewery) {
                return view('breweries.edit', compact('brewery'));
            }
        }
        
        // Intentar encontrar por nombre o slug
        $brewery = Brewery::where('name', $breweryParam)->first();
        if (!$brewery) {
            $brewery = Brewery::all()->first(function($b) use ($breweryParam) {
                return \Str::slug($b->name) === $breweryParam;
            });
        }
        
        if (!$brewery) {
            abort(404, 'Cervecería no encontrada');
        }
        
        return view('breweries.edit', compact('brewery'));
    }

    /**
     * Actualizar cervecería
     */
    public function update(Request $request, Brewery $brewery)
    {
        // Verificar que el usuario es el propietario o admin
        $this->authorize('update', $brewery);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'founded_year' => 'nullable|integer|min:1800|max:' . date('Y'),
            'website' => 'nullable|url|max:255',
            'visitable' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        // Manejar la carga de imagen
        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($brewery->image && $brewery->image !== 'breweries/default.jpg') {
                Storage::disk('public')->delete($brewery->image);
            }
            $validated['image'] = $request->file('image')->store('breweries/uploads', 'public');
        }

        // Actualizar la cervecería
        $brewery->update($validated);

        return redirect()->route('my_breweries')
            ->with('success', 'Cervecería actualizada exitosamente');
    }

    /**
     * Eliminar cervecería (solo para administradores)
     */
    public function destroy(Brewery $brewery)
    {
        // Verificar que el usuario es un administrador
        $this->authorize('delete', $brewery);

        // Eliminar imagen si existe
        if ($brewery->image && $brewery->image !== 'breweries/default.jpg') {
            Storage::disk('public')->delete($brewery->image);
        }

        $brewery->delete();

        return redirect()->route('breweries.index')
            ->with('success', 'Cervecería eliminada exitosamente');
    }
}

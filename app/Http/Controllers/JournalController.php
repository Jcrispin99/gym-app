<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Sequence;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Journal::with(['sequence', 'company'])
            ->orderBy('created_at', 'desc');

        // Filtrar por tipo si se proporciona
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $journals = $query->paginate(15);

        return Inertia::render('Journals/Index', [
            'journals' => $journals,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companies = Company::all();

        return Inertia::render('Journals/Create', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:journals,code',
            'type' => 'required|string',
            'is_fiscal' => 'boolean',
            'document_type_code' => 'nullable|string|max:2',
            'company_id' => 'nullable|exists:companies,id',
            
            // Datos de la secuencia (opcionales, con valores por defecto)
            'sequence_size' => 'nullable|integer|min:4|max:12',
            'step' => 'nullable|integer|min:1',
            'next_number' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Crear la secuencia primero
            $sequence = Sequence::create([
                'sequence_size' => $validated['sequence_size'] ?? 8,
                'step' => $validated['step'] ?? 1,
                'next_number' => $validated['next_number'] ?? 1,
            ]);

            // 2. Crear el journal asociado a la secuencia
            Journal::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'type' => $validated['type'],
                'is_fiscal' => $validated['is_fiscal'] ?? false,
                'document_type_code' => $validated['document_type_code'],
                'company_id' => $validated['company_id'],
                'sequence_id' => $sequence->id,
            ]);
        });

        return redirect()->route('journals.index')
            ->with('success', 'Diario creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Journal $journal)
    {
        $journal->load(['sequence', 'company']);

        return Inertia::render('Journals/Show', [
            'journal' => $journal,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Journal $journal)
    {
        $journal->load(['sequence', 'company']);
        $companies = Company::all();

        return Inertia::render('Journals/Edit', [
            'journal' => $journal,
            'companies' => $companies,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Journal $journal)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:journals,code,' . $journal->id,
            'type' => 'required|string',
            'is_fiscal' => 'boolean',
            'document_type_code' => 'nullable|string|max:2',
            'company_id' => 'nullable|exists:companies,id',
            
            // Actualización de secuencia (solo next_number es editable normalmente)
            'next_number' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($validated, $journal) {
            // Actualizar journal
            $journal->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'type' => $validated['type'],
                'is_fiscal' => $validated['is_fiscal'] ?? false,
                'document_type_code' => $validated['document_type_code'],
                'company_id' => $validated['company_id'],
            ]);

            // Actualizar next_number de la secuencia si se proporciona
            if (isset($validated['next_number'])) {
                $journal->sequence->update([
                    'next_number' => $validated['next_number'],
                ]);
            }
        });

        return redirect()->route('journals.index')
            ->with('success', 'Diario actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Journal $journal)
    {
        // Verificar si el journal tiene compras asociadas
        if ($journal->purchases()->count() > 0) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el diario porque tiene documentos asociados.');
        }

        DB::transaction(function () use ($journal) {
            $sequence = $journal->sequence;
            $journal->delete();
            
            // Eliminar la secuencia si no está siendo usada por otro journal
            if ($sequence && $sequence->journals()->count() === 0) {
                $sequence->delete();
            }
        });

        return redirect()->route('journals.index')
            ->with('success', 'Diario eliminado exitosamente.');
    }

    /**
     * Reset the sequence counter
     */
    public function resetSequence(Journal $journal)
    {
        $journal->sequence->update([
            'next_number' => 1,
        ]);

        return redirect()->back()
            ->with('success', 'Secuencia reiniciada a 1.');
    }
}

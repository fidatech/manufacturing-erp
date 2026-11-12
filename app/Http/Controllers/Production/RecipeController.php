<?php

namespace App\Http\Controllers\Production;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Recipes (formulas): what goes into one can or one bag of a product. */
class RecipeController extends Controller
{
    public static function methods(): array
    {
        return [
            'liquid' => __('Liquid: blended in the tank, packed in cans'),
            'powder' => __('Powder: dry blend, packed in bags'),
        ];
    }

    public function index(Request $request): View
    {
        Gate::authorize('production.view');

        $term = trim((string) $request->query('q'));
        $recipes = Recipe::with(['item.unit', 'lines.item.unit'])
            ->when($term !== '', fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', "%$term%")->orWhereHas('item', fn ($p) => $p->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%"))))
            ->orderBy('name')->paginate(per_page(25))->withQueryString();

        return view('production.recipes', ['recipes' => $recipes, 'term' => $term, 'base' => Rates::base()->code, 'methods' => self::methods()]);
    }

    public function create(): View
    {
        Gate::authorize('production.manage');

        return view('production.recipe-form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('production.manage');
        $this->save($request, new Recipe);

        return redirect()->route('recipes.index')->with('success', __('Recipe saved.'));
    }

    public function edit(Request $request): View
    {
        Gate::authorize('production.manage');

        return view('production.recipe-form', $this->formData(Recipe::with('lines')->findOrFail($request->route('id'))));
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('production.manage');
        $this->save($request, Recipe::findOrFail($request->route('id')));

        return redirect()->route('recipes.index')->with('success', __('Recipe saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('production.manage');

        $recipe = Recipe::findOrFail($request->route('id'));
        if (ProductionBatch::where('recipe_id', $recipe->id)->exists()) {
            throw new UserError(__('This recipe was already used for production. Switch it off instead of deleting it.'));
        }
        $recipe->delete();

        return redirect()->route('recipes.index')->with('success', __('Recipe deleted.'));
    }

    private function formData(?Recipe $recipe): array
    {
        return [
            'recipe' => $recipe,
            'products' => Item::where('type', 'product')->where('is_active', true)->orderBy('name')->get(),
            'materials' => Item::where('type', 'raw')->where('is_active', true)->with('unit')->orderBy('name')->get(),
            'methods' => self::methods(),
            'base' => Rates::base()->code,
        ];
    }

    private function save(Request $request, Recipe $recipe): void
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'method' => ['required', 'in:liquid,powder'],
            'pack_weight' => ['nullable', 'numeric', 'min:0'],
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
            'shelf_life_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $line) {
            if (empty($line['item_id']) || ! Num::isPositive($line['qty'] ?? 0)) {
                continue;
            }
            if ((int) $line['item_id'] === (int) $data['item_id']) {
                throw new UserError(__('A product cannot be an ingredient of itself.'));
            }
            // the same material twice is added together
            $lines[$line['item_id']] = Num::round(Num::add($lines[$line['item_id']] ?? 0, $line['qty']), 6);
        }
        if (! $lines) {
            throw new UserError(__('Add at least one ingredient with a quantity.'));
        }

        DB::transaction(function () use ($recipe, $data, $lines, $request) {
            $recipe->fill([
                'item_id' => $data['item_id'],
                'name' => ($data['name'] ?? null) ?: Item::find($data['item_id'])->name,
                'method' => $data['method'],
                'pack_weight' => Num::qty($data['pack_weight'] ?? 0),
                'extra_cost' => Num::round($data['extra_cost'] ?? 0, 4),
                'shelf_life_months' => (int) ($data['shelf_life_months'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ])->save();
            $recipe->lines()->delete();
            foreach ($lines as $itemId => $qty) {
                $recipe->lines()->create(['item_id' => $itemId, 'qty' => $qty]);
            }
        });
    }
}

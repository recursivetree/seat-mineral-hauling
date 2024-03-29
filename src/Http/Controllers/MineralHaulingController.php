<?php

namespace RecursiveTree\Seat\MineralHauling\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use RecursiveTree\Seat\PricesCore\Facades\PriceProviderSystem;
use RecursiveTree\Seat\TreeLib\Parser\Parser;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\InvTypeMaterial;
use Seat\Services\Items\PriceableEveType;
use Seat\Web\Http\Controllers\Controller;

class MineralHaulingController extends Controller
{
    public function calculator(Request $request)
    {
        $ores = InvType::whereIn("typeID", self::ALLOWED_ORE_TYPES)->get();

        return view('mineralhauling::calculator', compact("ores"));
    }

    const ALLOWED_ORE_TYPES = [
        62568,  //Arkonor
        62564,  //Bistot
        62560,  //Crokite
        62552,  //Gneiss
        62548,  //Hedbergite
        62544,  //Hemorphite
        62540,  //Jaspet
        62536,  //Kernite
        62586,  //Mercoxit
        62532,  //Omber
        62572,  //Spodumain
        62528,  //Plagioclase
        62524,  //Pyroxeres
        62520,  //Scordite
        62516,  //Veldspar
        62556,  //Dark Ochre
        75279   //Ytirium
    ];

    /*
     * Calculates
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'items' => 'required|string',
            'mode' => 'required|string|in:volume,price,transport,total',
            'iskm3' => 'required|numeric',
            'collateral' => 'required|numeric',
            'refinerate' => 'required|numeric',
            'priceprovider' => 'integer|nullable',
            'ints'=>'required|boolean'
        ]);

        //TODO make these settings
        $ore_modifier = floatval($request->refinerate);
        $m3_cost = floatval($request->iskm3);
        $collateral_modifier = floatval($request->collateral) / 100.0;
        $need_prices = $request->mode !== 'volume';

        // parse items
        $parser_result = Parser::parseItems($request->items);
        if ($parser_result->warning) {
            $request->session()->flash("warning", "There is something off with the items your entered. Please check if the data makes sense.");
        }
        if ($parser_result->items->isEmpty()) {
            $request->session()->flash("error", "You need to enter at least one item!");
            return redirect()->route("mineralhauling::calculate");
        }

        $products = [];
        foreach ($parser_result->items as $item) {
            // eliminate duplicate items, create a typeID -> amount mapping
            $products[$item->typeModel->typeID] = ($products[$item->typeModel->typeID] ?? 0) + $item->amount;
        }

        // find ore types that produce the desired output
        // load all potential recipes
        $recipes = InvTypeMaterial::select("invTypeMaterials.typeID", "invTypes.typeName", "invTypes.volume", "invTypes.portionSize")
            ->whereIn("materialTypeID", array_keys($products))
            ->whereIn("invTypeMaterials.typeID", self::ALLOWED_ORE_TYPES) // only include high-volume ore types
            ->join("invTypes", "invTypes.typeID", "invTypeMaterials.typeID")
            ->where("typeName", "like", "Compressed %")
            ->groupBy("invTypeMaterials.typeID", "invTypes.typeName", "invTypes.volume", "invTypes.portionSize")
            ->get();

        if($need_prices) {
            // load prices of recipe items
            $priceable_recipes = $recipes->map(function ($item) {
                return new PriceableEveType($item->typeID, 1);
            });
            PriceProviderSystem::getPrices($request->priceprovider, $priceable_recipes);
            $prices = [];
            foreach ($priceable_recipes as $priceable_recipe) {
                $price = $priceable_recipe->getPrice();
                // we don't want items the price provider fails to return a price
                if ($price == 0) {
                    $price = PHP_INT_MAX;
                }
                $prices[$priceable_recipe->getTypeID()] = $price;
            }
        }

        // generate variables for the solver
        $variables = [];
        foreach ($recipes as $recipe) {
            $reprocessing_products = InvTypeMaterial::where("typeID", $recipe->typeID)->get();

            if ($request->mode === "volume") {
                $cost = $recipe->volume;
            } else if ($request->mode === "price") {
                $cost = $prices[$recipe->typeID];
            } else if ($request->mode === "transport") {
                $cost = $recipe->volume * $m3_cost + $prices[$recipe->typeID] * $collateral_modifier;
            } else if ($request->mode === "total") {
                $cost = $prices[$recipe->typeID] + $recipe->volume * $m3_cost + $prices[$recipe->typeID] * $collateral_modifier;
            }

            $data = [
                'cost' => $cost,
                strval($recipe->typeID) => 1,
            ];

            foreach ($reprocessing_products as $product) {
                $data[strval($product->materialTypeID)] = $product->quantity * $ore_modifier / $recipe->portionSize;
            }

            $variables[strval($recipe->typeID)] = $data;
        }

        // generate constraints for the solver. We need at least the amount specified by the user
        $constraints = [];
        foreach ($products as $type_id => $amount) {
            $constraints[strval($type_id)] = ["min" => $amount];
        }

        // generate integer constraints
        $ints = [];
        if($request->ints) {
            foreach ($recipes as $recipe) {
                $ints[strval($recipe->typeID)] = $recipe->portionSize;
            }
        }


        return response()->json([
            "model" => [
                "optimize" => "cost",
                "opType" => "min",
                "variables" => $variables,
                "constraints" => $constraints,
                "ints"=>$ints,
            ]
        ]);
    }

    public function typeInfo($typeID)
    {
        return response()->json(InvType::where("typeID", $typeID)->value("typeName") ?? trans("web::seat.unknown"));
    }
}
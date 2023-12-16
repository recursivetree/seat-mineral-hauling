@extends('web::layouts.app')

@section('title', trans('mineralhauling::main.title'))
@section('page_header', trans('mineralhauling::main.title'))
@section('page_description', trans('mineralhauling::main.title'))

@section('content')

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ trans('mineralhauling::main.title') }}</h3>
        </div>
        <div class="card-body">
            <p>
                This calculator calculates the most efficient way to move minerals by searching for the ideal combination of ores to refine.
            </p>
            <div class="form-group">
                @csrf
                <div class="form-group">
                    <label for="items">Items</label>
                    <textarea
                            class="form-control"
                            id="items"
                            name="items"
                            placeholder="{{"INGAME INVENTORY WINDOW: (copy paste in list view mode)\n1MN Civilian Afterburner	1	Propulsion Module			5 m3	288.88 ISK\nCivilian Gatling Railgun	5	Energy Weapon			5 m3\nCivilian Relic Analyzer		Data Miners			5 m3\n\nMULTIBUY:\nTristan 100\nOmen 100\nTritanium 30000\n\nFITTINGS:\n[Pacifier, 2022 Scanner]\n\nCo-Processor II\nCo-Processor II\n\nMultispectrum Shield Hardener II\nMultispectrum Shield Hardener II\n\nSmall Tractor Beam II\nSmall Tractor Beam II"}}"
                            rows="22"></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="goal">Optimization Goal</label>
                <select id="goal" class="form-control">
                    <option value="volume" selected>Minimal Volume - Ignores Cost</option>
                    <option value="price">Minimal Price - Ignores Volume</option>
                    <option value="transport">Minimal Transport Prices - Ignores Ore Prices</option>
                    <option value="total">Total Prices - Includes Ore and Transport Prices</option>
                </select>
            </div>

            <div id="price-settings" class="d-none">
                <div class="form-group">
                    <label for="priceprovider">Price Provider</label>
                    @include("pricescore::utils.instance_selector",["id"=>"priceprovider","name"=>null,"instance_id"=>null])
                    <small class="text-muted">The source of the prices used in the weight function. Manage price providers in the <a href="{{route('pricescore::settings')}}">price provider settings</a>.</small>
                </div>
            </div>

            <div id="transport-settings" class="d-none">
                <div class="form-group">
                    <label for="iskm3">ISK/m3</label>
                    <input type="number" id="iskm3" class="form-control" value="1450" min="0">
                    <small class="text-muted">How much does it cost to transport 1m3 on the transport route.</small>
                </div>
                <div class="form-group">
                    <label for="collateral">Reward Collateral %</label>
                    <input type="number" id="collateral" class="form-control" value="1" min="0" max="100" step="0.1">
                    <small class="text-muted">Some hauling services add a percentage of the collateral to the reward.</small>
                </div>
            </div>

            <div class="form-group">
                <button type="button" class="btn btn-primary" id="calculate">Calculate</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ trans('mineralhauling::main.results') }}</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody id="table-content">

                </tbody>
            </table>
        </div>
    </div>
@endsection

@push("javascript")
    <script src="/mineralhauling/js/solver.js"></script>
    <script>
        const button = document.querySelector("#calculate")
        const table = document.querySelector("#table-content")
        const goal = document.querySelector("#goal")

        function updateAdditionalSettingsVisibility () {
            const mode = goal.value
            if(mode === "volume" || mode === "price") {
                document.querySelector("#transport-settings").classList.add("d-none")
            } else {
                document.querySelector("#transport-settings").classList.remove("d-none")
            }

            if(mode==="volume"){
                document.querySelector("#price-settings").classList.add("d-none")
            } else {
                document.querySelector("#price-settings").classList.remove("d-none")
            }
        }
        goal.addEventListener("change",updateAdditionalSettingsVisibility)
        updateAdditionalSettingsVisibility()

        async function getName(id) {
            const request = await fetch(`{{ str_replace('temporary','${id}',route('mineralhauling::type.info',["id"=>'temporary'])) }}`)
            return await request.json()
        }

        const worker = new Worker("{{asset("mineralhauling/js/worker-solver.js")}}");
        worker.onmessage = function (d) {
            const result = d.data
            button.textContent = "Calculate"
            button.disabled = false

            table.textContent = null

            for (const [key, value] of Object.entries(result)) {
                const tr = document.createElement("tr")

                const tdItem = document.createElement("td")
                const itemID = parseInt(key, 10)
                if (isNaN(itemID)) continue;
                tdItem.textContent = ""
                getName(itemID).then((name) => {
                    tdItem.textContent = name
                })

                const tdAmount = document.createElement("td")
                tdAmount.textContent = value

                tr.appendChild(tdItem)
                tr.appendChild(tdAmount)
                table.appendChild(tr)
            }
        }

        button.addEventListener("click", async function () {
            button.textContent = "Calculating..."
            button.disabled = true

            const request = await fetch("{{ route('mineralhauling::calculate') }}", {
                method: "POST",
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '{{csrf_token()}}'
                },
                body: JSON.stringify({
                    mode: goal.value,
                    priceprovider: parseInt(document.querySelector("#priceprovider").value),
                    iskm3: parseFloat(document.querySelector("#iskm3").value),
                    collateral: parseFloat(document.querySelector("#collateral").value),
                    items: document.querySelector("#items").value
                })
            })
            if (!request.ok) {
                button.textContent = "Failed to load data from server!"
                return
            }

            const response = await request.json()
            worker.postMessage(response.model);
        })
    </script>
@endpush

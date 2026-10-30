<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class AgentHelp
{
    public function handles(string $message): bool
    {
        $text = Str::lower(Str::ascii(trim($message)));

        return str_starts_with($text, '/')
            || preg_match('/^(?:[¿?\s]*)(?:ayuda|help|que puedo hacer|que puedes hacer|cuales son mis permisos|que permisos tengo|cual es mi rol)\b/', $text) === 1;
    }

    public function reply(User $user, Merchant $merchant, string $message, ?string $requestId = null, bool $clarification = false): array
    {
        Gate::forUser($user)->authorize('view', $merchant);
        $canIngest = Gate::forUser($user)->allows('ingest', $merchant);
        $definitions = [
            '/costos' => ['label' => 'Consultar costos', 'description' => 'Revisa costos y entregas finalizadas en el período seleccionado.', 'target' => 'costs-report'],
            '/estados' => ['label' => 'Estados por mensajero', 'description' => 'Elige un mensajero y consulta los estados de sus entregas.', 'target' => 'courier-summary'],
            '/entregas' => ['label' => 'Buscar entregas', 'description' => 'Busca por dirección, comercio o mensajero.', 'target' => 'delivery-search'],
            '/historial' => ['label' => 'Revisar historial', 'description' => 'Abre el detalle de una entrega para ver eventos y revisiones.', 'target' => 'deliveries-table'],
        ];
        if ($canIngest) {
            $definitions['/evento'] = ['label' => 'Crear entrega', 'description' => 'Crea una entrega y continúa su recorrido desde las acciones de la tabla.', 'target' => 'event-entry'];
        }
        $courier = $merchant->couriers()->orderBy('couriers.id')->first(['couriers.id', 'name']);
        $queries = [
            ['label' => 'Costos por período', 'description' => 'Respondo el costo total confirmado y cuántas entregas terminaron entregadas o fallidas.', 'prompt' => '¿Cuánto costaron las entregas la semana pasada?'],
            ['label' => 'Estados por mensajero', 'description' => 'Respondo cuántas entregas tiene cada estado para un mensajero y un período, según su fecha de creación.', 'prompt' => $courier ? '¿Qué estados tienen las entregas del mensajero ID '.$courier->id.' ('.$courier->name.') la semana pasada?' : null],
            ['label' => 'Buscar entregas', 'description' => 'Busco por dirección, comercio o mensajero y muestro estado, mensajero y costo confirmado. La búsqueda abarca todos los períodos.', 'prompt' => 'Busca entregas de Calle 80'],
        ];
        $command = Str::lower(Str::before(trim($message), ' '));
        $roleMessage = $canIngest
            ? 'Como operador puedes consultar entregas, costos, estados e historial de tus comercios asignados, además de ingresar eventos.'
            : 'Como lector puedes consultar entregas, costos, estados e historial de tus comercios asignados. No puedes ingresar eventos.';
        $text = $roleMessage.' En este chat puedo responder consultas de costos, estados por mensajero y búsquedas de entregas. El historial y el ingreso de eventos se consultan en el panel.';
        if ($clarification) {
            $text = 'No pude resolver esa consulta. Mi capacidad de respuesta todavía es limitada. Ahora puedo ayudarte con:';
        } elseif ($command === '/evento' && ! $canIngest) {
            $text = 'Tu rol no permite ingresar eventos. '.$roleMessage.' Ahora puedo ayudarte con:';
        } elseif (isset($definitions[$command])) {
            $text = 'Este comando ofrece un acceso directo al panel. '.$definitions[$command]['description'];
            $definitions = [$command => $definitions[$command]];
        } elseif (str_starts_with($command, '/') && ! in_array($command, ['/help', '/ayuda'], true)) {
            $text = 'No reconozco ese comando. '.$roleMessage.' Ahora puedo ayudarte con:';
        }
        $actions = [];
        foreach ($definitions as $name => $definition) {
            $actions[] = [...$definition, 'command' => $name, 'href' => route('operations', [], false).'#'.$definition['target']];
        }

        return [
            'request_id' => $requestId ?? (string) Str::uuid(),
            'kind' => $clarification ? 'clarification' : 'help',
            'message' => $text,
            'data' => null,
            'help' => ['role' => $canIngest ? 'operator' : 'reader', 'merchant_name' => $merchant->name, 'can_ingest' => $canIngest, 'queries' => $queries, 'actions' => $actions],
        ];
    }
}

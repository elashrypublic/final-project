<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Machine;
use App\Models\Sensor;
use App\Models\IndicatorSwitch;
class UpdateSensorsJob
{
    public function handle()
    {
        $machines = Machine::with('sensors')->get();

        foreach ($machines as $machine) {
            
        // If the machine is ON
if ($machine->status === 'ON') {

    // base values
    $rpm = rand(1200, 2500);

    // load depends on rpm
    $load = ($rpm / 2500) * 100 + rand(-5, 5);

    // vibration increases with load
    $vibration = ($load / 100) * 10 + rand(0, 2);

    // pressure depends on load
    $pressure = ($load / 100) * 20 + rand(-2, 2);

    // result
    $rate = ($rpm / 2500) * 200;

} else {

    // لو OFF كل حاجة صفر
    $rpm = 0;
    $load = 0;
    $vibration = 0;
    $pressure = 0;
    $rate = 0;
}

    // if ($machine->status === 'OFF') continue;

    foreach ($machine->sensors as $sensor) {

        switch ($sensor->name) {

    case 'rpm':
        $value = $rpm;
        break;

    case 'load':
        $value = $load;
        break;

    case 'vibration':
        $value = $vibration;
        break;

    case 'PT1':
    case 'PT2':
    case 'PT3':
    case 'PT4':
        $value = $pressure;
        break;

    case 'rate':
        $value = $rate;
        break;

    case 'total':
        $value = $sensor->value + $rate;
        break;

    case 'hopper':
        $value = $sensor->value + rand(-50, 50);
        break;

    case 'buffer':
        $value = $sensor->value + rand(-50, 50);
        break;

    default:
    // 1. log unknown sensor
    app(\App\Services\LogService::class)->log(
        type: 'unknown_sensor_detected',
        message: "New/Unknown sensor detected: {$sensor->name}",
        severity: 'warning',
        metadata: [
            'machine_id' => $sensor->machine_id,
            'sensor_name' => $sensor->name
        ]
    );

    // 2. treat it safely
    $value = $sensor->value;
}

        $sensor->update(['value' => $value]);

        // ✅ log update
        app(\App\Services\LogService::class)->log(
            type: 'sensor_updated',
            message: "Sensor {$sensor->name} updated",
            severity: 'info',
            metadata: [
                'machine_id' => $machine->id,
                'sensor' => $sensor->name,
                'value' => $value
            ]
        );

        //  anomalies

        // pressure high
        if ($sensor->type === 'pressure' && $value > 18) {
            $this->logAnomaly($machine, $sensor, $value, 'High pressure');
        }

        // vibration high
        if ($sensor->name === 'vibration' && $value > 8) {
            $this->logAnomaly($machine, $sensor, $value, 'High vibration');
        }

        // motor overload
        if ($sensor->name === 'load' && $value > 90) {
            $this->logAnomaly($machine, $sensor, $value, 'Motor overload');
        }

        // hopper overflow
        if ($sensor->name === 'hopper' && $value > 95) {
            $this->logAnomaly($machine, $sensor, $value, 'Hopper overflow');
        }
    }


$switches = IndicatorSwitch::all();

foreach ($switches as $switch) {

    switch ($switch->name) {

        case 'Motor A':
        case 'Motor B':
            // ON لو rpm > 0
            $rpmSensor = $machine->sensors->firstWhere('name', 'rpm');
            $value = ($rpmSensor && $rpmSensor->value > 0) ? 1 : 0;
            break;

        case 'Conveyor A':
        case 'Conveyor B':
            // ON لو machine شغالة
            $value = $machine->status === 'ON' ? 1 : 0;
            break;

        case 'Cooling Pump':
            // ON لو vibration عالي
            $vibration = $machine->sensors->firstWhere('name', 'vibration');
            $value = ($vibration && $vibration->value > 5) ? 1 : 0;
            break;

        default:
            $value = 0;
    }

    if ($switch->value != $value) {

    $switch->update(['value' => $value]);

    app(\App\Services\LogService::class)->log(
        type: 'switch_changed',
        message: "{$switch->name} turned " . ($value ? 'ON' : 'OFF'),
        severity: 'info',
        metadata: [
            'switch' => $switch->name,
            'value' => $value,
            'machine_id' => $machine->id
        ]
    );
}
}
 
}

   }


    private function logAnomaly($machine, $sensor, $value, $message)
{
    app(\App\Services\LogService::class)->log(
        type: 'anomaly_detected',
        message: $message,
        severity: 'critical',
        metadata: [
            'machine_id' => $machine->id,
            'sensor' => $sensor->name,
            'value' => $value
        ]
    );
}
}



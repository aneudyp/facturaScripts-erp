# Guía Completa: Plugin de Nómina y Gestión de Gastos para FacturaScripts

## Índice
1. [Introducción](#introducción)
2. [Arquitectura del Plugin](#arquitectura-del-plugin)
3. [Estructura de Directorios](#estructura-de-directorios)
4. [Modelos de Datos](#modelos-de-datos)
5. [Controladores](#controladores)
6. [Vistas y Templates](#vistas-y-templates)
7. [Integración con Bancos EEUU](#integración-con-bancos-eeuu)
8. [Integración con OpenAI](#integración-con-openai)
9. [Web para Empleados](#web-para-empleados)
10. [Modificación del Dashboard](#modificación-del-dashboard)
11. [Configuración e Instalación](#configuración-e-instalación)
12. [Seguridad y Permisos](#seguridad-y-permisos)
13. [Testing y Deployment](#testing-y-deployment)

## Introducción

Este documento proporciona una guía completa para desarrollar un plugin profesional para FacturaScripts que gestione:

- **Nómina de empleados** con cálculos automáticos
- **Documentos de empleados** (contratos, certificados, etc.)
- **Gastos de empresa por categoría** con seguimiento detallado
- **Integración con bancos EEUU** para registro automático de gastos
- **Integración con OpenAI** para análisis inteligente de gastos y nómina
- **Web para empleados** para consulta de recibos e información personal
- **KPIs en Dashboard** para gestión de empleados y nómina

## Arquitectura del Plugin

### Estructura de Directorios

```
Plugins/
└── NominaGastos/
    ├── PluginInfo.php
    ├── Model/
    │   ├── Empleado.php
    │   ├── Nomina.php
    │   ├── NominaLinea.php
    │   ├── GastoEmpresa.php
    │   ├── CategoriaGasto.php
    │   ├── DocumentoEmpleado.php
    │   ├── IntegracionBanco.php
    │   └── AnalisisOpenAI.php
    ├── Controller/
    │   ├── EditEmpleado.php
    │   ├── ListEmpleado.php
    │   ├── EditNomina.php
    │   ├── ListNomina.php
    │   ├── EditGastoEmpresa.php
    │   ├── ListGastoEmpresa.php
    │   ├── EmployeeWeb.php
    │   └── DashboardExtension.php
    ├── View/
    │   ├── EditEmpleado.html.twig
    │   ├── ListEmpleado.html.twig
    │   ├── EditNomina.html.twig
    │   ├── ListNomina.html.twig
    │   ├── EditGastoEmpresa.html.twig
    │   ├── ListGastoEmpresa.html.twig
    │   ├── EmployeeWeb.html.twig
    │   └── Dashboard/
    │       ├── NominaStats.html.twig
    │       └── EmployeeStats.html.twig
    ├── Table/
    │   ├── empleados.xml
    │   ├── nominas.xml
    │   ├── nominas_lineas.xml
    │   ├── gastos_empresa.xml
    │   ├── categorias_gasto.xml
    │   ├── documentos_empleado.xml
    │   ├── integraciones_banco.xml
    │   └── analisis_openai.xml
    ├── Data/
    │   ├── categorias_gasto.csv
    │   └── tipos_documento_empleado.csv
    ├── Translation/
    │   ├── es_ES.json
    │   └── en_US.json
    ├── Assets/
    │   ├── CSS/
    │   │   └── nomina-gastos.css
    │   └── JS/
    │       ├── nomina-calculator.js
    │       ├── bank-integration.js
    │       └── openai-analysis.js
    ├── Lib/
    │   ├── BankIntegration/
    │   │   ├── ChaseBank.php
    │   │   ├── BankOfAmerica.php
    │   │   └── WellsFargo.php
    │   ├── OpenAI/
    │   │   ├── ExpenseAnalyzer.php
    │   │   └── PayrollAnalyzer.php
    │   └── PayrollCalculator.php
    └── Cron/
        ├── BankSyncCron.php
        └── PayrollGenerationCron.php
```

## Modelos de Datos

### 1. Modelo Empleado

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Model;

use FacturaScripts\Core\Model\Base\ModelCore;

class Empleado extends ModelCore
{
    public $codempleado;
    public $nombre;
    public $apellidos;
    public $dni;
    public $email;
    public $telefono;
    public $direccion;
    public $fecha_nacimiento;
    public $fecha_ingreso;
    public $fecha_baja;
    public $salario_base;
    public $tipo_contrato;
    public $departamento;
    public $cargo;
    public $activo;
    public $codagente; // Relación con agente de FacturaScripts
    
    public static function primaryColumn(): string
    {
        return 'codempleado';
    }
    
    public static function tableName(): string
    {
        return 'empleados';
    }
    
    public function install(): string
    {
        return 'CREATE TABLE IF NOT EXISTS ' . static::tableName() . ' (
            codempleado VARCHAR(10) NOT NULL PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellidos VARCHAR(100) NOT NULL,
            dni VARCHAR(20) NOT NULL UNIQUE,
            email VARCHAR(100),
            telefono VARCHAR(20),
            direccion TEXT,
            fecha_nacimiento DATE,
            fecha_ingreso DATE NOT NULL,
            fecha_baja DATE NULL,
            salario_base DECIMAL(10,2) NOT NULL DEFAULT 0,
            tipo_contrato VARCHAR(50),
            departamento VARCHAR(100),
            cargo VARCHAR(100),
            activo BOOLEAN DEFAULT TRUE,
            codagente VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }
}
```

### 2. Modelo Nómina

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Model;

use FacturaScripts\Core\Model\Base\ModelCore;

class Nomina extends ModelCore
{
    public $codnomina;
    public $codempleado;
    public $periodo;
    public $fecha_inicio;
    public $fecha_fin;
    public $salario_base;
    public $horas_extra;
    public $bonificaciones;
    public $deducciones;
    public $salario_neto;
    public $estado;
    public $fecha_generacion;
    public $fecha_pago;
    
    public static function primaryColumn(): string
    {
        return 'codnomina';
    }
    
    public static function tableName(): string
    {
        return 'nominas';
    }
    
    public function install(): string
    {
        return 'CREATE TABLE IF NOT EXISTS ' . static::tableName() . ' (
            codnomina VARCHAR(10) NOT NULL PRIMARY KEY,
            codempleado VARCHAR(10) NOT NULL,
            periodo VARCHAR(20) NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            salario_base DECIMAL(10,2) NOT NULL DEFAULT 0,
            horas_extra DECIMAL(8,2) DEFAULT 0,
            bonificaciones DECIMAL(10,2) DEFAULT 0,
            deducciones DECIMAL(10,2) DEFAULT 0,
            salario_neto DECIMAL(10,2) NOT NULL DEFAULT 0,
            estado VARCHAR(20) DEFAULT "pendiente",
            fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_pago DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (codempleado) REFERENCES empleados(codempleado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }
}
```

### 3. Modelo Gasto Empresa

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Model;

use FacturaScripts\Core\Model\Base\ModelCore;

class GastoEmpresa extends ModelCore
{
    public $codgasto;
    public $descripcion;
    public $importe;
    public $fecha;
    public $codcategoria;
    public $codempleado;
    public $metodo_pago;
    public $referencia_banco;
    public $comprobante;
    public $estado;
    public $analisis_openai;
    
    public static function primaryColumn(): string
    {
        return 'codgasto';
    }
    
    public static function tableName(): string
    {
        return 'gastos_empresa';
    }
}
```

## Controladores

### 1. Controlador de Empleados

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\NominaGastos\Model\Empleado;

class EditEmpleado extends EditController
{
    public function getModelClassName(): string
    {
        return 'Empleado';
    }
    
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'employees';
        $data['title'] = 'employee';
        $data['icon'] = 'fas fa-user';
        return $data;
    }
    
    protected function createViews()
    {
        parent::createViews();
        
        // Vista de documentos del empleado
        $this->addListView('ListDocumentoEmpleado', 'DocumentoEmpleado', 'documents', 'fas fa-file-alt');
        
        // Vista de nóminas del empleado
        $this->addListView('ListNomina', 'Nomina', 'payroll', 'fas fa-money-bill-wave');
    }
}
```

### 2. Controlador Web para Empleados

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Plugins\NominaGastos\Model\Empleado;
use FacturaScripts\Plugins\NominaGastos\Model\Nomina;
use Symfony\Component\HttpFoundation\Response;

class EmployeeWeb extends Controller
{
    public $empleado;
    public $nominas = [];
    
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'employee-portal';
        $data['title'] = 'employee-portal';
        $data['icon'] = 'fas fa-user-circle';
        return $data;
    }
    
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        // Obtener empleado por usuario actual
        $this->empleado = new Empleado();
        $this->empleado->loadFromCode($user->codagente);
        
        if ($this->empleado->exists()) {
            $this->loadNominas();
        }
    }
    
    private function loadNominas()
    {
        $nomina = new Nomina();
        $where = [
            new DataBaseWhere('codempleado', $this->empleado->codempleado)
        ];
        
        $this->nominas = $nomina->all($where, ['fecha_generacion' => 'DESC'], 0, 12);
    }
}
```

## Integración con Bancos EEUU

### 1. Clase Base para Integración Bancaria

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Lib\BankIntegration;

abstract class BankIntegrationBase
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    
    public function __construct($apiKey, $apiSecret, $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = $baseUrl;
    }
    
    abstract public function getTransactions($startDate, $endDate);
    abstract public function categorizeTransaction($transaction);
    abstract public function syncExpenses();
}
```

### 2. Integración con Chase Bank

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Lib\BankIntegration;

class ChaseBank extends BankIntegrationBase
{
    public function getTransactions($startDate, $endDate)
    {
        $url = $this->baseUrl . '/transactions';
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        
        $response = $this->makeRequest('GET', $url, $params);
        return $this->parseTransactions($response);
    }
    
    public function categorizeTransaction($transaction)
    {
        // Lógica de categorización automática
        $description = strtolower($transaction['description']);
        
        if (strpos($description, 'gas') !== false || strpos($description, 'fuel') !== false) {
            return 'TRANSPORTE';
        }
        
        if (strpos($description, 'restaurant') !== false || strpos($description, 'food') !== false) {
            return 'ALIMENTACION';
        }
        
        if (strpos($description, 'office') !== false || strpos($description, 'supplies') !== false) {
            return 'OFICINA';
        }
        
        return 'OTROS';
    }
    
    private function makeRequest($method, $url, $params = [])
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

## Integración con OpenAI

### 1. Analizador de Gastos con OpenAI

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Lib\OpenAI;

class ExpenseAnalyzer
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }
    
    public function analyzeExpense($gasto)
    {
        $prompt = $this->buildPrompt($gasto);
        
        $response = $this->makeRequest([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un experto en análisis financiero empresarial. Analiza gastos y proporciona insights útiles.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.3
        ]);
        
        return $this->parseResponse($response);
    }
    
    private function buildPrompt($gasto)
    {
        return "Analiza el siguiente gasto empresarial:
        
        Descripción: {$gasto->descripcion}
        Importe: {$gasto->importe}
        Fecha: {$gasto->fecha}
        Categoría: {$gasto->codcategoria}
        
        Proporciona:
        1. Análisis de la categorización
        2. Recomendaciones de optimización
        3. Patrones detectados
        4. Sugerencias de ahorro";
    }
    
    private function makeRequest($data)
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

## Modificación del Dashboard

### 1. Extensión del Dashboard

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Controller;

use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Controller\Dashboard as CoreDashboard;
use FacturaScripts\Plugins\NominaGastos\Model\Empleado;
use FacturaScripts\Plugins\NominaGastos\Model\Nomina;
use FacturaScripts\Plugins\NominaGastos\Model\GastoEmpresa;

class DashboardExtension
{
    use ExtensionsTrait;
    
    public function addPayrollStats($dashboard)
    {
        // Estadísticas de nómina
        $nomina = new Nomina();
        $totalNominas = $nomina->count();
        $nominasPendientes = $nomina->count(['estado' => 'pendiente']);
        $totalSalarios = $nomina->sum('salario_neto');
        
        $dashboard->stats['payroll'] = [
            'total-nominas' => $totalNominas,
            'nominas-pendientes' => $nominasPendientes,
            'total-salarios' => $totalSalarios
        ];
        
        // Estadísticas de empleados
        $empleado = new Empleado();
        $totalEmpleados = $empleado->count(['activo' => true]);
        $empleadosNuevos = $empleado->count([
            'fecha_ingreso' => date('Y-m-d', strtotime('-30 days'))
        ]);
        
        $dashboard->stats['employees'] = [
            'total-empleados' => $totalEmpleados,
            'empleados-nuevos' => $empleadosNuevos
        ];
        
        // Estadísticas de gastos
        $gasto = new GastoEmpresa();
        $totalGastos = $gasto->sum('importe');
        $gastosMes = $gasto->sum('importe', [
            'fecha' => date('Y-m-01')
        ]);
        
        $dashboard->stats['expenses'] = [
            'total-gastos' => $totalGastos,
            'gastos-mes' => $gastosMes
        ];
    }
}
```

### 2. Template del Dashboard Extendido

```twig
{# Dashboard/NominaStats.html.twig #}
<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-3">
            <div class="card-body p-2 text-white bg-primary">
                <h2 class="h5 text-center mb-0">
                    {{ trans('payroll-stats') }}
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <tr>
                        <td>{{ trans('total-payrolls') }}</td>
                        <td class="text-right">{{ fsc.stats.payroll['total-nominas'] }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('pending-payrolls') }}</td>
                        <td class="text-right text-warning">{{ fsc.stats.payroll['nominas-pendientes'] }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('total-salaries') }}</td>
                        <td class="text-right">{{ money(fsc.stats.payroll['total-salarios']) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-3">
            <div class="card-body p-2 text-white bg-success">
                <h2 class="h5 text-center mb-0">
                    {{ trans('employee-stats') }}
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <tr>
                        <td>{{ trans('total-employees') }}</td>
                        <td class="text-right">{{ fsc.stats.employees['total-empleados'] }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('new-employees') }}</td>
                        <td class="text-right">{{ fsc.stats.employees['empleados-nuevos'] }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-3">
            <div class="card-body p-2 text-white bg-warning">
                <h2 class="h5 text-center mb-0">
                    {{ trans('expense-stats') }}
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <tr>
                        <td>{{ trans('total-expenses') }}</td>
                        <td class="text-right">{{ money(fsc.stats.expenses['total-gastos']) }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('month-expenses') }}</td>
                        <td class="text-right">{{ money(fsc.stats.expenses['gastos-mes']) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
```

## Web para Empleados

### 1. Template de Portal de Empleado

```twig
{# EmployeeWeb.html.twig #}
{% extends "Master/MenuTemplate.html.twig" %}

{% block body %}
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="fas fa-user-circle"></i>
                {{ trans('employee-portal') }}
            </h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ trans('personal-information') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>{{ trans('name') }}:</strong></td>
                            <td>{{ fsc.empleado.nombre }} {{ fsc.empleado.apellidos }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ trans('email') }}:</strong></td>
                            <td>{{ fsc.empleado.email }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ trans('phone') }}:</strong></td>
                            <td>{{ fsc.empleado.telefono }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ trans('department') }}:</strong></td>
                            <td>{{ fsc.empleado.departamento }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ trans('position') }}:</strong></td>
                            <td>{{ fsc.empleado.cargo }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ trans('payroll-summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h6>{{ trans('base-salary') }}</h6>
                            <h4 class="text-primary">{{ money(fsc.empleado.salario_base) }}</h4>
                        </div>
                        <div class="col-6">
                            <h6>{{ trans('contract-type') }}</h6>
                            <h4 class="text-info">{{ fsc.empleado.tipo_contrato }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ trans('payroll-history') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ trans('period') }}</th>
                                    <th>{{ trans('base-salary') }}</th>
                                    <th>{{ trans('bonuses') }}</th>
                                    <th>{{ trans('deductions') }}</th>
                                    <th>{{ trans('net-salary') }}</th>
                                    <th>{{ trans('status') }}</th>
                                    <th>{{ trans('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {% for nomina in fsc.nominas %}
                                <tr>
                                    <td>{{ nomina.periodo }}</td>
                                    <td>{{ money(nomina.salario_base) }}</td>
                                    <td>{{ money(nomina.bonificaciones) }}</td>
                                    <td>{{ money(nomina.deducciones) }}</td>
                                    <td><strong>{{ money(nomina.salario_neto) }}</strong></td>
                                    <td>
                                        <span class="badge badge-{{ nomina.estado == 'pagado' ? 'success' : 'warning' }}">
                                            {{ trans(nomina.estado) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ fsc.url() }}?action=download&codnomina={{ nomina.codnomina }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> {{ trans('download') }}
                                        </a>
                                    </td>
                                </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
```

## Configuración e Instalación

### 1. PluginInfo.php

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos;

use FacturaScripts\Core\Base\PluginDeploy;

class PluginInfo
{
    public function install(): string
    {
        $deploy = new PluginDeploy();
        $deploy->deploy();
        return 'Plugin de Nómina y Gastos instalado correctamente.';
    }
    
    public function uninstall(): string
    {
        return 'Plugin de Nómina y Gastos desinstalado correctamente.';
    }
    
    public function update(): string
    {
        $deploy = new PluginDeploy();
        $deploy->deploy();
        return 'Plugin de Nómina y Gastos actualizado correctamente.';
    }
}
```

### 2. Configuración de Permisos

```php
<?php
// En el controlador principal del plugin
class NominaGastosPermissions
{
    public static function getPermissions(): array
    {
        return [
            'nomina-gastos' => [
                'employees' => 'Gestionar empleados',
                'payroll' => 'Gestionar nóminas',
                'expenses' => 'Gestionar gastos',
                'reports' => 'Ver reportes',
                'admin' => 'Administrar plugin'
            ]
        ];
    }
}
```

## Seguridad y Permisos

### 1. Validación de Datos

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Lib;

class SecurityValidator
{
    public static function validateEmployeeData($data): array
    {
        $errors = [];
        
        if (empty($data['nombre'])) {
            $errors[] = 'El nombre es obligatorio';
        }
        
        if (empty($data['dni'])) {
            $errors[] = 'El DNI es obligatorio';
        } elseif (!self::validateDNI($data['dni'])) {
            $errors[] = 'El DNI no es válido';
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        return $errors;
    }
    
    private static function validateDNI($dni): bool
    {
        // Implementar validación de DNI según el país
        return preg_match('/^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', strtoupper($dni));
    }
}
```

### 2. Encriptación de Datos Sensibles

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Lib;

class DataEncryption
{
    private $encryptionKey;
    
    public function __construct($key)
    {
        $this->encryptionKey = $key;
    }
    
    public function encrypt($data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encryptedData): string
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
```

## Testing y Deployment

### 1. Tests Unitarios

```php
<?php
namespace FacturaScripts\Plugins\NominaGastos\Test;

use PHPUnit\Framework\TestCase;
use FacturaScripts\Plugins\NominaGastos\Model\Empleado;
use FacturaScripts\Plugins\NominaGastos\Lib\PayrollCalculator;

class PayrollCalculatorTest extends TestCase
{
    public function testCalculateGrossSalary()
    {
        $calculator = new PayrollCalculator();
        $result = $calculator->calculateGrossSalary(2000, 40, 10);
        
        $this->assertEquals(2400, $result);
    }
    
    public function testCalculateDeductions()
    {
        $calculator = new PayrollCalculator();
        $result = $calculator->calculateDeductions(2400, 0.15, 0.05);
        
        $this->assertEquals(360, $result['taxes']);
        $this->assertEquals(120, $result['social_security']);
    }
}
```

### 2. Script de Deployment

```bash
#!/bin/bash
# deploy-plugin.sh

echo "Desplegando plugin de Nómina y Gastos..."

# Verificar dependencias
composer install --no-dev --optimize-autoloader

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate --no-interaction

# Limpiar caché
php bin/console cache:clear

# Configurar permisos
chmod -R 755 Plugins/NominaGastos/

echo "Plugin desplegado correctamente."
```

## Conclusión

Esta guía proporciona una base sólida para desarrollar un plugin profesional de nómina y gestión de gastos para FacturaScripts. El plugin incluye:

- **Gestión completa de empleados** con documentos y contratos
- **Sistema de nómina** con cálculos automáticos
- **Gestión de gastos** con categorización inteligente
- **Integración bancaria** para sincronización automática
- **Análisis con OpenAI** para insights financieros
- **Portal web para empleados** con acceso a información personal
- **Dashboard extendido** con KPIs relevantes
- **Seguridad robusta** con validaciones y encriptación

El plugin está diseñado para ser escalable, mantenible y seguir las mejores prácticas de desarrollo en FacturaScripts.
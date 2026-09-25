<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use DateTimeImmutable;
use PDO;

/**
 * Pipeline operativo COMEX (equivalente a Server Action de
 * src/app/(dashboard)/[tenantId]/operations en App Router).
 *
 * Al crear Importación/Exportación se siembran 13 etapas:
 * Evaluación (5) + Ejecución (8).
 */
final class Pipeline
{
    public const PENDING = 'PENDING';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const COMPLETED = 'COMPLETED';
    public const BLOCKED = 'BLOCKED';

    public const FASE_EVALUACION = 'EVALUACION';
    public const FASE_EJECUCION = 'EJECUCION';

    /** Días entre fechas estimadas consecutivas. */
    public const PASO_DIAS = 5;

    /** @var list<array{codigo: string, nombre: string, fase: string}> */
    public const CATALOGO = [
        ['codigo' => 'SOLICITUD', 'nombre' => 'Solicitud', 'fase' => self::FASE_EVALUACION],
        ['codigo' => 'COTIZACION', 'nombre' => 'Cotización', 'fase' => self::FASE_EVALUACION],
        ['codigo' => 'VIABILIDAD', 'nombre' => 'Análisis de viabilidad', 'fase' => self::FASE_EVALUACION],
        ['codigo' => 'LANDED', 'nombre' => 'Evaluación financiera', 'fase' => self::FASE_EVALUACION],
        ['codigo' => 'APROBACION', 'nombre' => 'Aprobación', 'fase' => self::FASE_EVALUACION],
        ['codigo' => 'PO', 'nombre' => 'Orden de compra (PO)', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'PAGO', 'nombre' => 'Pago', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'EMBARQUE', 'nombre' => 'Embarque', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'TRACKING', 'nombre' => 'Tracking', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'DOCS', 'nombre' => 'Documentos de transporte', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'ADUANA', 'nombre' => 'Despacho aduanero DIN/DUS', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'ENTREGA', 'nombre' => 'Entrega', 'fase' => self::FASE_EJECUCION],
        ['codigo' => 'CIERRE', 'nombre' => 'Cierre operativo', 'fase' => self::FASE_EJECUCION],
    ];

    /** @return list<array{codigo: string, nombre: string, fase: string, orden: int}> */
    public static function catalogo(string $tipo = Operaciones::IMPORTACION): array
    {
        $tipo = strtoupper($tipo);
        $out = [];
        foreach (self::CATALOGO as $i => $row) {
            $nombre = $row['nombre'];
            if ($row['codigo'] === 'ADUANA') {
                if ($tipo === Operaciones::EXPORTACION) {
                    $nombre = 'Despacho aduanero DUS';
                } elseif ($tipo === Operaciones::IMPORTACION) {
                    $nombre = 'Despacho aduanero DIN';
                }
            }
            $out[] = [
                'codigo' => $row['codigo'],
                'nombre' => $nombre,
                'fase' => $row['fase'],
                'orden' => $i + 1,
            ];
        }
        return $out;
    }

    public static function sembrar(int $operacionId, string $tipo, string $fecha): void
    {
        if ($operacionId <= 0) {
            throw new ApiException('Operación inválida para el pipeline', 400);
        }
        $pdo = Connection::app();
        $existe = $pdo->prepare('SELECT COUNT(*) FROM comex_operacion_etapas WHERE operacion_id = ?');
        $existe->execute([$operacionId]);
        if ((int) $existe->fetchColumn() > 0) {
            return;
        }
        $base = self::fechaBase($fecha);
        $ins = $pdo->prepare(
            'INSERT INTO comex_operacion_etapas
                (operacion_id, codigo, nombre, fase, orden, estado, responsable, fecha_estimada, fecha_real, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = crm_now();
        foreach (self::catalogo($tipo) as $i => $meta) {
            $est = $base->modify('+' . ($i * self::PASO_DIAS) . ' days')->format('Y-m-d');
            $estado = $i === 0 ? self::IN_PROGRESS : self::PENDING;
            $ins->execute([
                $operacionId,
                $meta['codigo'],
                $meta['nombre'],
                $meta['fase'],
                $meta['orden'],
                $estado,
                '',
                $est,
                null,
                $now,
                $now,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraOperacion(int $operacionId): array
    {
        $op = Operaciones::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        self::sembrar($operacionId, (string) $op['tipo'], (string) $op['fecha']);
        $etapas = self::etapasDe($operacionId);
        $hoy = self::hoy();
        foreach ($etapas as $i => $et) {
            $etapas[$i] = self::anotar($et, $hoy);
            $etapas[$i]['bitacora'] = self::bitacora((int) $et['id']);
        }
        $pendEval = [];
        foreach ($op['items'] ?? [] as $it) {
            if (is_array($it) && Operaciones::esPendienteCatalogo($it)) {
                $pendEval[] = $it;
            }
        }
        return [
            'operacion' => $op,
            'catalogo' => self::catalogo((string) $op['tipo']),
            'etapas' => $etapas,
            'actual' => self::etapaActual($etapas),
            'progreso' => self::progreso($etapas),
            'alertas' => self::alertasDe($etapas),
            'estados' => [self::PENDING, self::IN_PROGRESS, self::COMPLETED, self::BLOCKED],
            'kanban_estado' => self::kanbanPorEstado($etapas),
            'items_evaluacion' => $pendEval,
        ];
    }

    /**
     * Tablero global: columnas = 13 etapas, tarjetas = operaciones en esa etapa actual.
     *
     * @return array<string, mixed>
     */
    public static function tablero(): array
    {
        $ops = Operaciones::listar();
        $hoy = self::hoy();
        $tarjetas = [];
        foreach ($ops as $op) {
            $id = (int) $op['id'];
            self::sembrar($id, (string) $op['tipo'], (string) $op['fecha']);
            $etapas = self::etapasDe($id);
            foreach ($etapas as $i => $et) {
                $etapas[$i] = self::anotar($et, $hoy);
            }
            $actual = self::etapaActual($etapas);
            $alertas = self::alertasDe($etapas);
            $prog = self::progreso($etapas);
            $tarjetas[] = [
                'id' => $id,
                'folio' => $op['folio'],
                'tipo' => $op['tipo'],
                'estado_op' => $op['estado'],
                'fecha' => $op['fecha'],
                'referencia' => $op['referencia'] ?? '',
                'etapa' => $actual,
                'progreso' => $prog,
                'atrasada' => $alertas['atrasadas'] > 0,
                'bloqueada' => $alertas['bloqueadas'] > 0,
                'alertas' => $alertas,
            ];
        }

        $columnas = [];
        foreach (self::catalogo() as $meta) {
            $cards = [];
            foreach ($tarjetas as $t) {
                if (is_array($t['etapa']) && ($t['etapa']['codigo'] ?? '') === $meta['codigo']) {
                    $cards[] = $t;
                }
            }
            $columnas[] = $meta + [
                'tarjetas' => $cards,
                'atrasadas' => count(array_filter($cards, static fn (array $c): bool => !empty($c['atrasada']))),
                'bloqueadas' => count(array_filter($cards, static fn (array $c): bool => !empty($c['bloqueada']))),
            ];
        }

        $eval = array_values(array_filter($columnas, static fn (array $c): bool => $c['fase'] === self::FASE_EVALUACION));
        $ejec = array_values(array_filter($columnas, static fn (array $c): bool => $c['fase'] === self::FASE_EJECUCION));

        return [
            'catalogo' => self::catalogo(),
            'operaciones' => $tarjetas,
            'columnas' => $columnas,
            'fases' => [
                ['codigo' => self::FASE_EVALUACION, 'nombre' => 'Evaluación', 'columnas' => $eval],
                ['codigo' => self::FASE_EJECUCION, 'nombre' => 'Ejecución', 'columnas' => $ejec],
            ],
            'kpis' => [
                'operaciones' => count($tarjetas),
                'atrasadas' => count(array_filter($tarjetas, static fn (array $t): bool => !empty($t['atrasada']))),
                'bloqueadas' => count(array_filter($tarjetas, static fn (array $t): bool => !empty($t['bloqueada']))),
                'completadas' => count(array_filter($tarjetas, static fn (array $t): bool => (int) ($t['progreso']['pct'] ?? 0) === 100)),
            ],
            'estados' => [self::PENDING, self::IN_PROGRESS, self::COMPLETED, self::BLOCKED],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function crearOperacion(array $data): array
    {
        $op = Operaciones::crear($data);
        return self::paraOperacion((int) $op['id']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function actualizarEtapa(int $etapaId, array $data): array
    {
        $row = self::porId($etapaId);
        if ($row === null) {
            throw new ApiException('Etapa no encontrada', 404);
        }
        $estado = strtoupper(trim((string) ($data['estado'] ?? $row['estado'])));
        if (!in_array($estado, [self::PENDING, self::IN_PROGRESS, self::COMPLETED, self::BLOCKED], true)) {
            throw new ApiException('estado debe ser PENDING, IN_PROGRESS, COMPLETED o BLOCKED', 400);
        }
        $responsable = trim((string) ($data['responsable'] ?? $row['responsable'] ?? ''));
        $fechaEst = self::fechaOpcional($data['fecha_estimada'] ?? $row['fecha_estimada'] ?? null);
        $fechaReal = self::fechaOpcional($data['fecha_real'] ?? $row['fecha_real'] ?? null);
        if ($estado === self::COMPLETED && $fechaReal === null) {
            $fechaReal = self::hoy();
        }
        if ($estado !== self::COMPLETED) {
            if (array_key_exists('fecha_real', $data) && ($data['fecha_real'] === '' || $data['fecha_real'] === null)) {
                $fechaReal = null;
            }
        }
        $codigo = strtoupper((string) ($row['codigo'] ?? ''));
        $cierraStock = $estado === self::COMPLETED && in_array($codigo, ['ENTREGA', 'CIERRE'], true);
        $opId = (int) $row['operacion_id'];
        if ($cierraStock) {
            Operaciones::exigirCatalogoOficial($opId);
            Operaciones::aplicarStockPendiente($opId);
        }
        $now = crm_now();
        $upd = Connection::app()->prepare(
            'UPDATE comex_operacion_etapas
             SET estado = ?, responsable = ?, fecha_estimada = ?, fecha_real = ?, updated_at = ?
             WHERE id = ?'
        );
        $upd->execute([$estado, $responsable, $fechaEst, $fechaReal, $now, $etapaId]);

        $comentario = trim((string) ($data['comentario'] ?? $data['bitacora'] ?? ''));
        if ($comentario !== '') {
            $autor = trim((string) ($data['autor'] ?? 'COMEX'));
            $ins = Connection::app()->prepare(
                'INSERT INTO comex_etapa_bitacora (etapa_id, comentario, autor, created_at) VALUES (?, ?, ?, ?)'
            );
            $ins->execute([$etapaId, $comentario, $autor === '' ? 'COMEX' : $autor, $now]);
        }

        if ($estado === self::COMPLETED) {
            self::abrirSiguiente($opId, (int) $row['orden']);
        }

        return self::paraOperacion($opId);
    }

    /**
     * Completa la etapa actual e inicia la siguiente.
     *
     * @return array<string, mixed>
     */
    public static function avanzar(int $operacionId, ?string $comentario = null, string $autor = 'COMEX'): array
    {
        $pack = self::paraOperacion($operacionId);
        $actual = $pack['actual'];
        if (!is_array($actual)) {
            throw new ApiException('El pipeline ya está cerrado', 409);
        }
        $data = ['estado' => self::COMPLETED];
        if ($comentario !== null && trim($comentario) !== '') {
            $data['comentario'] = $comentario;
            $data['autor'] = $autor;
        }
        return self::actualizarEtapa((int) $actual['id'], $data);
    }

    public static function esAtrasada(array $etapa, ?string $hoy = null): bool
    {
        $estado = (string) ($etapa['estado'] ?? '');
        if ($estado === self::COMPLETED) {
            return false;
        }
        $est = trim((string) ($etapa['fecha_estimada'] ?? ''));
        if ($est === '') {
            return false;
        }
        $hoy = $hoy ?? self::hoy();
        return $est < $hoy;
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_operacion_etapas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Etapas de una operación (sin bitácora) para KPIs y dashboard.
     *
     * @return list<array<string, mixed>>
     */
    public static function etapas(int $operacionId): array
    {
        $op = Operaciones::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        self::sembrar($operacionId, (string) $op['tipo'], (string) $op['fecha']);
        $etapas = self::etapasDe($operacionId);
        $hoy = self::hoy();
        foreach ($etapas as $i => $et) {
            $etapas[$i] = self::anotar($et, $hoy);
        }
        return $etapas;
    }

    /** @return list<array<string, mixed>> */
    private static function etapasDe(int $operacionId): array
    {
        $stmt = Connection::app()->prepare(
            'SELECT * FROM comex_operacion_etapas WHERE operacion_id = ? ORDER BY orden ASC'
        );
        $stmt->execute([$operacionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    private static function bitacora(int $etapaId): array
    {
        $stmt = Connection::app()->prepare(
            'SELECT * FROM comex_etapa_bitacora WHERE etapa_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$etapaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $etapa
     * @return array<string, mixed>
     */
    private static function anotar(array $etapa, string $hoy): array
    {
        $etapa['atrasada'] = self::esAtrasada($etapa, $hoy);
        $etapa['bloqueada'] = (string) ($etapa['estado'] ?? '') === self::BLOCKED;
        $etapa['alerta'] = !empty($etapa['bloqueada']) || !empty($etapa['atrasada']);
        return $etapa;
    }

    /**
     * @param list<array<string, mixed>> $etapas
     * @return array<string, mixed>|null
     */
    private static function etapaActual(array $etapas): ?array
    {
        foreach ($etapas as $et) {
            if ((string) ($et['estado'] ?? '') !== self::COMPLETED) {
                return $et;
            }
        }
        return $etapas === [] ? null : $etapas[count($etapas) - 1];
    }

    /**
     * @param list<array<string, mixed>> $etapas
     * @return array{hechas: int, total: int, pct: int}
     */
    private static function progreso(array $etapas): array
    {
        $total = count($etapas);
        $hechas = 0;
        foreach ($etapas as $et) {
            if ((string) ($et['estado'] ?? '') === self::COMPLETED) {
                $hechas++;
            }
        }
        return [
            'hechas' => $hechas,
            'total' => $total,
            'pct' => $total > 0 ? (int) round(($hechas / $total) * 100) : 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $etapas
     * @return array{atrasadas: int, bloqueadas: int}
     */
    private static function alertasDe(array $etapas): array
    {
        $atrasadas = 0;
        $bloqueadas = 0;
        foreach ($etapas as $et) {
            if (!empty($et['atrasada'])) {
                $atrasadas++;
            }
            if (!empty($et['bloqueada'])) {
                $bloqueadas++;
            }
        }
        return ['atrasadas' => $atrasadas, 'bloqueadas' => $bloqueadas];
    }

    /**
     * @param list<array<string, mixed>> $etapas
     * @return array<string, list<array<string, mixed>>>
     */
    private static function kanbanPorEstado(array $etapas): array
    {
        $out = [
            self::PENDING => [],
            self::IN_PROGRESS => [],
            self::COMPLETED => [],
            self::BLOCKED => [],
        ];
        foreach ($etapas as $et) {
            $st = (string) ($et['estado'] ?? self::PENDING);
            if (!isset($out[$st])) {
                $st = self::PENDING;
            }
            $out[$st][] = $et;
        }
        return $out;
    }

    private static function abrirSiguiente(int $operacionId, int $ordenHecho): void
    {
        $stmt = Connection::app()->prepare(
            'SELECT id, estado FROM comex_operacion_etapas
             WHERE operacion_id = ? AND orden > ? ORDER BY orden ASC LIMIT 1'
        );
        $stmt->execute([$operacionId, $ordenHecho]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($next)) {
            return;
        }
        if ((string) $next['estado'] === self::PENDING) {
            $upd = Connection::app()->prepare(
                'UPDATE comex_operacion_etapas SET estado = ?, updated_at = ? WHERE id = ?'
            );
            $upd->execute([self::IN_PROGRESS, crm_now(), (int) $next['id']]);
        }
    }

    private static function fechaBase(string $fecha): DateTimeImmutable
    {
        $fecha = trim($fecha);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fecha);
        return $dt instanceof DateTimeImmutable ? $dt : new DateTimeImmutable('today');
    }

    private static function fechaOpcional(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s) === 1) {
            return substr($s, 0, 10);
        }
        throw new ApiException('Fecha inválida (use YYYY-MM-DD)', 400);
    }

    public static function hoy(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d');
    }
}

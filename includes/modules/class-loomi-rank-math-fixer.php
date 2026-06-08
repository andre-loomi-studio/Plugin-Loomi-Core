<?php
/**
 * Auto-corrige @type inválidos que o Rank Math Free deixa o usuário selecionar
 * sem validar contra schema.org.
 *
 * Caso típico: o usuário escolhe "Geriatric" na configuração do Knowledge Graph
 * pensando que identifica a empresa como geriátrica, mas `Geriatric` é
 * `MedicalSpecialty`, não um tipo de Business. O Google Rich Results Test
 * sinaliza como "Unrecognized type".
 *
 * Este módulo intercepta o filtro `rank_math/json_ld`, percorre os nós e
 * remapeia tipos inválidos pra alternativas válidas adequadas.
 *
 * Toggle: Settings → Loomi Studio → Schema → "Auto-corrigir Rank Math".
 * Filtro: `loomi_rm_invalid_type_fixes` permite estender o mapping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Rank_Math_Fixer implements Loomi_Module {

	/**
	 * Tipos que o RM Free permite escolher mas não são tipos válidos de Business
	 * em schema.org (são MedicalSpecialty ou similares). Mapping → tipo válido equivalente.
	 *
	 * @var array<string,string>
	 */
	const INVALID_TYPE_FIXES = [
		'Geriatric'       => 'MedicalBusiness',
		'Pediatric'       => 'MedicalBusiness',
		'Cardiology'      => 'MedicalBusiness',
		'Oncology'        => 'MedicalBusiness',
		'Neurology'       => 'MedicalBusiness',
		'Dermatology'     => 'MedicalBusiness',
		'Psychiatry'      => 'MedicalBusiness',
		'Surgery'         => 'MedicalBusiness',
		'PhysicalTherapy' => 'MedicalBusiness',
		'Dentistry'       => 'Dentist',
		'Pharmacy'        => 'Pharmacy', // já é válido — no-op defensivo
	];

	public static function register() : void {
		if ( ! Settings_Repository::get_bool( 'loomi_rm_fix_invalid_types' ) ) {
			return;
		}
		add_filter( 'rank_math/json_ld', [ __CLASS__, 'fix_invalid_types' ], 100, 2 );
	}

	/**
	 * @param mixed $data
	 * @param mixed $jsonld
	 * @return mixed
	 */
	public static function fix_invalid_types( $data, $jsonld = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$fixes = (array) apply_filters( 'loomi_rm_invalid_type_fixes', self::INVALID_TYPE_FIXES );
		return self::walk_and_fix( $data, $fixes );
	}

	/**
	 * @param mixed                $data
	 * @param array<string,string> $fixes
	 * @return mixed
	 */
	private static function walk_and_fix( $data, array $fixes ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $key => $value ) {
			if ( $key === '@type' ) {
				$data[ $key ] = self::remap( $value, $fixes );
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = self::walk_and_fix( $value, $fixes );
			}
		}
		return $data;
	}

	/**
	 * @param mixed                $value
	 * @param array<string,string> $fixes
	 * @return mixed
	 */
	private static function remap( $value, array $fixes ) {
		if ( is_string( $value ) ) {
			return $fixes[ $value ] ?? $value;
		}
		if ( is_array( $value ) ) {
			$mapped = [];
			foreach ( $value as $t ) {
				if ( ! is_string( $t ) ) {
					$mapped[] = $t;
					continue;
				}
				$t = $fixes[ $t ] ?? $t;
				if ( ! in_array( $t, $mapped, true ) ) {
					$mapped[] = $t;
				}
			}
			return count( $mapped ) === 1 ? $mapped[0] : $mapped;
		}
		return $value;
	}
}

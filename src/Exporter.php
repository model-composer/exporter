<?php namespace Model\Exporter;

use Model\Cache\Cache;
use Model\Exporter\DataExporters\Csv;

class Exporter
{
	public static function beginExport(DataProvider $provider, string $final_dir, string $format, int $paginate = 50, array $options = []): string
	{
		$id = uniqid();

		$cache = Cache::getCacheAdapter();
		$cacheItem = $cache->getItem('model-exporter-' . $id . '-main');
		$cacheItem->set([
			'paginate' => $paginate,
			'tot' => $provider->getTot($paginate),
			'current' => 0,
			'format' => $format,
			'options' => $options,
			'tmp_dir' => $options['tmp_dir'] ?? null,
			'final_dir' => $final_dir,
		]);
		$cacheItem->expiresAfter(3600 * 24);
		$cache->save($cacheItem);

		return $id;
	}

	public static function next(DataProvider $provider, string $id): array
	{
		$cache = Cache::getCacheAdapter();
		$cacheItem = $cache->getItem('model-exporter-' . $id . '-main');
		if (!$cacheItem->isHit())
			throw new \Exception('Export not found', 404);

		$exportData = $cacheItem->get();

		$exporter = self::getDataExporter($exportData['format']);

		$currentPage = $exportData['current'] + 1;
		if ($currentPage === 1)
			$exporter->setHeader($provider->getHeader(), $exportData['options']);

		$data = $provider->getNext($exportData['paginate'], $currentPage);
		$converted = $exporter->convert($data, $exportData['options']);

		self::saveFile($id, $currentPage, $converted, $exportData['tmp_dir']);

		if ($currentPage === $exportData['tot']) {
			$cache->deleteItem('model-exporter-' . $id . '-main');

			$tmp_dir = self::normalizeTmpDir($exportData['tmp_dir']);

			$exporter->finalize($exportData['final_dir'] . $id . '.' . $exporter->getFileExtension(), $tmp_dir . $id, $exportData['tot'], $exportData['options']);

			return [
				'status' => 'finished',
			];
		} else {
			$cacheItem->set([
				...$exportData,
				'current' => $currentPage,
			]);
			$cache->save($cacheItem);

			return [
				'status' => 'running',
				'tot' => $exportData['tot'],
				'current' => $currentPage,
			];
		}
	}

	private static function getDataExporter(string $format): DataExporter
	{
		switch ($format) {
			case 'csv':
				return new Csv;

			default:
				throw new \Exception('"' . $format . '" exporter not found', 404);
		}
	}

	private static function saveFile(string $id, int $page, string $data, ?string $tmp_dir)
	{
		$tmp_dir = self::normalizeTmpDir($tmp_dir);
		if (!is_dir($tmp_dir . 'model-exports' . DIRECTORY_SEPARATOR . $id))
			mkdir($tmp_dir . 'model-exports' . DIRECTORY_SEPARATOR . $id, 0777, true);

		file_put_contents($tmp_dir . 'model-exports' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $page, $data);
	}

	private static function normalizeTmpDir(?string $tmp_dir): string
	{
		if ($tmp_dir === null)
			$tmp_dir = sys_get_temp_dir();
		if (!str_ends_with($tmp_dir, DIRECTORY_SEPARATOR))
			$tmp_dir = DIRECTORY_SEPARATOR;
		return $tmp_dir;
	}
}

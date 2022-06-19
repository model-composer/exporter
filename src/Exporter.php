<?php namespace Model\Exporter;

use Model\Cache\Cache;
use Model\Exporter\DataExporters\Csv;

class Exporter
{
	public static function beginExport(DataProvider $provider, string $dir, string $format, int $paginate = 50, array $options = []): string
	{
		$tot = $provider->getTot($paginate);
		if ($tot <= 0)
			throw new \Exception('No items to export');

		if (!str_ends_with($dir, DIRECTORY_SEPARATOR))
			$dir .= DIRECTORY_SEPARATOR;

		$id = uniqid();

		$cache = Cache::getCacheAdapter();
		$cacheItem = $cache->getItem('model-exporter-' . $id . '-main');
		$cacheItem->set([
			'paginate' => $paginate,
			'tot' => $tot,
			'current' => 0,
			'format' => $format,
			'options' => $options,
			'dir' => $dir,
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

		self::saveFile($id, $currentPage, $converted, $exportData['dir']);

		if ($currentPage === $exportData['tot']) {
			$cache->deleteItem('model-exporter-' . $id . '-main');

			$exporter->finalize($exportData['dir'] . $id . '.' . $exporter->getFileExtension(), $exportData['dir'] . $id, $exportData['tot'], $exportData['options']);

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

	private static function saveFile(string $id, int $page, string $data, ?string $dir)
	{
		if (!is_dir($dir . 'model-exports' . DIRECTORY_SEPARATOR . $id))
			mkdir($dir . 'model-exports' . DIRECTORY_SEPARATOR . $id, 0777, true);

		file_put_contents($dir . 'model-exports' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $page, $data);
	}
}

<?php namespace Model\Exporter;

use Model\Cache\Cache;

class Exporter
{
	public static function beginExport(DataProvider $provider, string $dir, string $format, int $paginate = 100, array $options = []): string
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
		if ($exporter->hasHeaderAt($currentPage))
			$exporter->setHeader($provider->getHeader(), $exportData['options']);

		$data = $provider->getNext($exportData['paginate'], $currentPage);
		$converted = $exporter->convert($data, $exportData['options']);

		$fileExt = $exporter->getFileExtension();

		self::saveFile($id, $currentPage, $converted, $fileExt, $exportData['dir']);

		if ($currentPage === $exportData['tot']) {
			$final_filename = $exportData['dir'] . $id . '.' . $fileExt;
			$tmp_folder = $exportData['dir'] . $id;

			$tmp_files = [];
			for ($c = 1; $c <= $exportData['tot']; $c++)
				$tmp_files[] = $tmp_folder . DIRECTORY_SEPARATOR . $c . '.' . $fileExt;

			$exporter->finalize($tmp_files, $final_filename, $exportData['tot'], $exportData['options']);

			foreach ($tmp_files as $tmp_file)
				unlink($tmp_file);
			rmdir($tmp_folder);

			$cache->deleteItem('model-exporter-' . $id . '-main');

			return [
				'status' => 'finished',
				'file' => $final_filename,
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
				return new \Model\Exporter\DataExporters\Csv;

			case 'xlsx':
				return new \Model\Exporter\DataExporters\Xlsx;

			case 'html':
				return new \Model\Exporter\DataExporters\Html;

			default:
				throw new \Exception('"' . $format . '" exporter not found', 404);
		}
	}

	private static function saveFile(string $id, int $page, string $data, string $ext, ?string $dir)
	{
		if (!is_dir($dir . $id))
			mkdir($dir . $id, 0777, true);

		file_put_contents($dir . $id . DIRECTORY_SEPARATOR . $page . '.' . $ext, $data);
	}
}

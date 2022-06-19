<?php namespace Model\Exporter;

interface DataProvider
{
	public function getHeader(): array;

	public function getTot(int $paginate): int;

	public function getNext(int $paginate, int $current): iterable;
}

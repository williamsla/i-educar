<?php

use App\Models\LegacyInstitutionDocument;

class InstituicaoDocumentacaoController extends ApiCoreController
{
    protected function insertDocuments()
    {
        LegacyInstitutionDocument::create([
            'instituicao_id' => request()->integer('instituicao_id'),
            'titulo_documento' => request()->string('titulo_documento'),
            'url_documento' => request()->string('url_documento'),
            'ref_usuario_cad' => request()->integer('ref_usuario_cad'),
            'ref_cod_escola' => request()->integer('ref_cod_escola'),
            'ano' => request()->integer('ano') ?: (int) date('Y'),
        ]);

        return [
            'id' => LegacyInstitutionDocument::query()->select('id')->max('id'),
        ];
    }

    protected function getDocuments()
    {
        $instituitionId = request()->integer('instituicao_id');
        $year = request()->integer('ano');

        $documents = LegacyInstitutionDocument::query()
            ->select([
                'id',
                'titulo_documento',
                'url_documento',
                'ref_usuario_cad',
                'ref_cod_escola',
                'ano',
            ])
            ->where('instituicao_id', $instituitionId)
            ->when($year, fn ($query) => $query->where('ano', $year))
            ->orderByDesc('ano')
            ->orderByDesc('id')
            ->get()
            ->toArray();

        return ['documentos' => $documents];
    }

    protected function getYears()
    {
        $instituitionId = request()->integer('instituicao_id');

        $years = LegacyInstitutionDocument::query()
            ->where('instituicao_id', $instituitionId)
            ->distinct()
            ->orderByDesc('ano')
            ->pluck('ano')
            ->toArray();

        return ['anos' => $years];
    }

    protected function deleteDocuments()
    {
        $documentId = request()->integer('id');

        return LegacyInstitutionDocument::query()
            ->whereKey($documentId)
            ->delete();
    }

    public function Gerar()
    {
        if ($this->isRequestFor('get', 'insertDocuments')) {
            $this->appendResponse($this->insertDocuments());
        } elseif ($this->isRequestFor('get', 'getDocuments')) {
            $this->appendResponse($this->getDocuments());
        } elseif ($this->isRequestFor('get', 'getYears')) {
            $this->appendResponse($this->getYears());
        } elseif ($this->isRequestFor('get', 'deleteDocuments')) {
            $this->appendResponse($this->deleteDocuments());
        }
    }
}

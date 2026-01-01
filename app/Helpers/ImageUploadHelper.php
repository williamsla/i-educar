<?php
// app/Helpers/ImageUploadHelper.php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageUploadHelper
{
    /**
     * Processa upload de imagem e retorna o caminho para salvar no banco
     * 
     * @param array|null $file Array do $_FILES['campo']
     * @param string|null $currentPath Caminho atual (para deletar)
     * @param string $folder Pasta dentro de storage/app/public
     * @param int $maxSize Tamanho máximo em bytes (padrão: 2MB)
     * @return string|null Caminho relativo para salvar no banco
     */
    public static function uploadImage(?array $file, ?string $currentPath = null, string $folder = 'logos', int $maxSize = 2097152): ?string
    {
        // Se não enviou arquivo válido, mantém o atual
        if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return $currentPath;
        }

        // Validações de tamanho
        if ($file['size'] > $maxSize) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: ' . ($maxSize / 1024 / 1024) . 'MB.');
        }

        // Validação de tipo MIME
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        
        // Usa mime_content_type que é mais confiável que $file['type']
        $mimeType = mime_content_type($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \Exception('Formato inválido. Use JPG, PNG, GIF, WebP ou SVG.');
        }

        // Valida se é realmente uma imagem (exceto SVG)
        if ($mimeType !== 'image/svg+xml') {
            $imageInfo = @getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                throw new \Exception('Arquivo não é uma imagem válida.');
            }
        }

        // Determina extensão correta
        $extension = self::getExtensionFromMime($mimeType);
        
        // Nome único e seguro
        $fileName = Str::slug(pathinfo($file['name'], PATHINFO_FILENAME), '_') 
                  . '_' . time() . '_' . Str::random(8) . '.' . $extension;

        // Remove imagem antiga se existir
        if ($currentPath && self::imageExists($currentPath)) {
            self::deleteImage($currentPath);
        }

        try {
            // Garante que o diretório existe
            $storagePath = storage_path('app/public/' . $folder);
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Caminho completo do arquivo de destino
            $destinationPath = $storagePath . '/' . $fileName;
            
            // Move o arquivo do temp para o storage
            if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                throw new \Exception('Erro ao mover arquivo para o storage.');
            }

            // Define permissões corretas
            chmod($destinationPath, 0644);

            // Retorna caminho relativo (ex: 'logos/nome_arquivo.png')
            $relativePath = $folder . '/' . $fileName;
            
            // Log de sucesso
            Log::info('✅ Imagem enviada com sucesso', [
                'arquivo' => $fileName,
                'pasta' => $folder,
                'caminho_relativo' => $relativePath,
                'tamanho' => $file['size']
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('❌ Erro ao fazer upload de imagem: ' . $e->getMessage(), [
                'arquivo' => $file['name'] ?? 'desconhecido',
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Erro ao salvar a imagem no servidor: ' . $e->getMessage());
        }
    }

    /**
     * Obtém extensão do arquivo baseado no MIME type
     */
    private static function getExtensionFromMime(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        return $mimeToExt[$mimeType] ?? 'jpg';
    }

    /**
     * Verifica se uma imagem existe no storage
     */
    public static function imageExists(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Se for URL completa, verifica de forma diferente
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return self::isUrlAccessible($path);
        }

        // Remove 'public/' do início se existir
        $cleanPath = preg_replace('/^public\//', '', $path);
        
        // Verifica no storage público
        return Storage::disk('public')->exists($cleanPath);
    }

    /**
     * Obtém a URL pública de uma imagem
     */
    public static function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Se já for uma URL completa, retorna ela mesma
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Remove 'public/' do início se existir
        $cleanPath = preg_replace('/^public\//', '', $path);
        
        try {
            if (Storage::disk('public')->exists($cleanPath)) {
                return Storage::disk('public')->url($cleanPath);
            }
            
            // Fallback: tenta verificar se existe fisicamente
            $physicalPath = storage_path('app/public/' . $cleanPath);
            if (file_exists($physicalPath)) {
                return asset('storage/' . $cleanPath);
            }
            
        } catch (\Exception $e) {
            Log::warning('Erro ao obter URL da imagem: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Deleta uma imagem do storage
     */
    public static function deleteImage(?string $path): bool
    {
        if (empty($path) || !self::imageExists($path)) {
            return false;
        }

        try {
            // Se for URL externa, não tenta deletar
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return true;
            }

            // Remove 'public/' do início se existir
            $cleanPath = preg_replace('/^public\//', '', $path);
            
            return Storage::disk('public')->delete($cleanPath);
            
        } catch (\Exception $e) {
            Log::error('Erro ao deletar imagem: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma URL de imagem é válida e acessível
     */
    public static function validateImageUrl(?string $url): bool
    {
        if (empty($url)) {
            return true; // URL vazia é válida (para limpar o campo)
        }

        // Valida formato da URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Verifica extensões comuns (opcional)
        $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'];
        foreach ($imageExtensions as $ext) {
            if (stripos($url, $ext) !== false) {
                return true;
            }
        }

        // Aceita URLs sem extensão específica
        return true;
    }

    /**
     * Verifica se uma URL é acessível
     */
    public static function isUrlAccessible(string $url): bool
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ImageValidator/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }

    /**
     * Obtém as dimensões de uma imagem
     */
    public static function getImageDimensions(?string $path): ?array
    {
        if (!$path || !self::imageExists($path)) {
            return null;
        }

        try {
            // Se for URL externa
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $headers = get_headers($path, 1);
                if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'image/') === 0) {
                    // Para URLs externas, pode usar GD ou ImageMagick via stream
                    $image = @imagecreatefromstring(file_get_contents($path));
                    if ($image) {
                        $width = imagesx($image);
                        $height = imagesy($image);
                        imagedestroy($image);
                        return ['width' => $width, 'height' => $height];
                    }
                }
                return null;
            }

            // Para arquivos locais
            $cleanPath = preg_replace('/^public\//', '', $path);
            $fullPath = storage_path('app/public/' . $cleanPath);
            
            if (file_exists($fullPath)) {
                $size = @getimagesize($fullPath);
                if ($size) {
                    return ['width' => $size[0], 'height' => $size[1]];
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Erro ao obter dimensões da imagem: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Cria diretórios necessários se não existirem
     */
    public static function ensureDirectoriesExist(array $folders): void
    {
        foreach ($folders as $folder) {
            $path = storage_path('app/public/' . $folder);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}

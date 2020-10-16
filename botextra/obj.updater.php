<?php
    #Client Updater
    class updater extends botExtraClient {
        function update() {
            $result = $this->get('http://client.botextra.com/client/checkVersion');
            if (!$result) return $this->answer('HATA','Lutfen manuel guncelleme yapin','','ERR007');
            $lastVersion = json_decode($result);

            if ($this->version !== $lastVersion) {
                $result = $this->get('http://client.botextra.com/client/checkFiles?script='.$this->script);
                if (!$result) return $this->answer('HATA','Lutfen manuel guncelleme yapin','','ERR007');
            
                $checkFiles = json_decode($result);

                foreach($checkFiles as $filePath => $icerik) {
                    if (!is_writable($filePath)) return $this->answer('HATA',"$filePath dosyasinin yazma izni yok",'','ERR007');
                }

                foreach($checkFiles as $filePath => $icerik) {
                    $file = @fopen($filePath,'w');
                    if (!$file) return $this->answer('HATA','Lutfen manuel guncelleme yapin','','ERR007');
                    fwrite($file,$icerik);
                    fclose($file);
                }
                return $this->answer('OK','Guncellendi');
            } else {
                return $this->answer('OK','Zaten Guncel');
            }
        }
        
    }
?>

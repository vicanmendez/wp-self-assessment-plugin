# Guía de Instalación - WP Self Assessment

## 📋 Requisitos Previos

Antes de instalar el plugin, asegúrate de que tu sitio WordPress cumple con los siguientes requisitos:

### Requisitos Mínimos
- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior (recomendado 8.0+)
- **MySQL**: 5.6 o superior (recomendado 8.0+)
- **Memoria PHP**: 256MB mínimo (recomendado 512MB)
- **Tiempo de ejecución**: 30 segundos mínimo

### Requisitos Recomendados
- **WordPress**: 6.0 o superior
- **PHP**: 8.1 o superior
- **MySQL**: 8.0 o superior
- **Memoria PHP**: 512MB o más
- **Tiempo de ejecución**: 60 segundos

## 🔑 Obtener API Key de Google Gemini

### Paso 1: Crear Cuenta
1. Ve a [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Inicia sesión con tu cuenta de Google
3. Si no tienes cuenta, créala gratuitamente

### Paso 2: Generar API Key
1. Una vez en Google AI Studio, haz clic en "Create API Key"
2. Selecciona "Create API key in new project" o usa un proyecto existente
3. Copia la API Key generada (guárdala en un lugar seguro)

### Paso 3: Configurar Límites (Opcional)
1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Selecciona tu proyecto
3. Ve a "APIs & Services" → "Quotas"
4. Configura límites de uso según tus necesidades

## 🛠️ Instalación del Plugin

### Método 1: Subida Manual (Recomendado)

1. **Descargar el Plugin**:
   - Descarga el archivo ZIP del plugin
   - No descomprimas el archivo

2. **Subir a WordPress**:
   - Ve a tu panel de WordPress
   - Navega a **Plugins → Añadir nuevo**
   - Haz clic en **"Subir plugin"**
   - Selecciona el archivo ZIP
   - Haz clic en **"Instalar ahora"**

3. **Activar el Plugin**:
   - Una vez instalado, haz clic en **"Activar plugin"**
   - Verás un mensaje de confirmación

### Método 2: Instalación por FTP

1. **Descomprimir**:
   - Extrae el archivo ZIP
   - Obtén la carpeta `wp-self-assessment`

2. **Subir por FTP**:
   - Conecta a tu servidor via FTP
   - Navega a `/wp-content/plugins/`
   - Sube la carpeta `wp-self-assessment`

3. **Activar**:
   - Ve al panel de WordPress
   - Ve a **Plugins**
   - Busca "WP Self Assessment"
   - Haz clic en **"Activar"**

## ⚙️ Configuración Inicial

### Paso 1: Configurar API Key

1. **Acceder a Configuración**:
   - Ve a **Autoevaluaciones → Configuración**
   - En el menú lateral de WordPress

2. **Ingresar API Key**:
   - Pega tu API Key de Gemini en el campo correspondiente
   - Haz clic en **"Mostrar/Ocultar"** para verificar que esté correcta

3. **Configurar Límites**:
   - Establece el máximo de preguntas por sesión (recomendado: 10)
   - Guarda la configuración

### Paso 2: Configurar reCAPTCHA (Opcional)

1. **Obtener Claves reCAPTCHA v3**:
   - Ve a [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
   - Haz clic en **"+"** para crear un nuevo sitio
   - Selecciona **reCAPTCHA v3** → **Invisible reCAPTCHA**
   - Agrega tu dominio
   - Copia las claves Site Key y Secret Key

**Nota**: reCAPTCHA v3 es invisible y no requiere interacción del usuario.

2. **Configurar en WordPress**:
   - Ve a **Autoevaluaciones → Configuración**
   - Pega las claves en los campos correspondientes
   - Guarda la configuración

### Paso 3: Crear Primera Materia

1. **Acceder a Materias**:
   - Ve a **Autoevaluaciones → Materias**
   - Haz clic en **"Agregar Nueva"**

2. **Completar Información**:
   ```
   Nombre: Matemáticas Avanzadas
   Grado: 1er Año Universitario
   Descripción: Curso de matemáticas para estudiantes de ingeniería
   Temario Manual: (opcional)
   URL del PDF: (opcional)
   ```

3. **Analizar PDF (Opcional)**:
   - Si tienes un programa en PDF, ingresa la URL
   - Haz clic en **"Analizar PDF con IA"**
   - Revisa el temario generado
   - Acepta o edita según sea necesario

4. **Guardar**:
   - Haz clic en **"Guardar Materia"**
   - Verás confirmación de éxito

## 🧪 Verificar Instalación

### Prueba Básica

1. **Crear Página de Prueba**:
   - Ve a **Páginas → Añadir nueva**
   - Título: "Autoevaluación de Prueba"
   - Contenido: `[wpsa_autoevaluacion]`
   - Publicar

2. **Probar Funcionalidad**:
   - Visita la página en el frontend
   - Verifica que aparezcan las materias
   - Intenta iniciar una autoevaluación

### Verificar Configuración

1. **Dashboard**:
   - Ve a **Autoevaluaciones → Dashboard**
   - Verifica que aparezcan las estadísticas

2. **Reportes**:
   - Ve a **Autoevaluaciones → Reportes**
   - Confirma que no hay errores

## 🔧 Configuración Avanzada

### Personalizar Shortcode

```php
// Shortcode básico
[wpsa_autoevaluacion]

// Con parámetros específicos
[wpsa_autoevaluacion materia_id="1" tema="Álgebra" modalidad="ejercicios"]

// En template PHP
echo do_shortcode('[wpsa_autoevaluacion materia_id="2"]');
```

### Configurar Permisos de Usuario

1. **Roles de Usuario**:
   - Solo usuarios con `manage_options` pueden acceder al admin
   - Todos los usuarios pueden usar el shortcode

2. **Personalizar Acceso**:
   ```php
   // En functions.php del tema
   add_filter('wpsa_user_can_access', function($can_access, $user_id) {
       // Lógica personalizada
       return $can_access;
   }, 10, 2);
   ```

### Optimizar Rendimiento

1. **Caché**:
   - Instala un plugin de caché (WP Rocket, W3 Total Cache)
   - Excluye las páginas con autoevaluaciones del caché

2. **Base de Datos**:
   - Limpia autoevaluaciones antiguas periódicamente
   - Optimiza las tablas de la base de datos

## 🚨 Solución de Problemas

### Error: "Plugin no se activa"

**Causas Comunes**:
- Versión de PHP incompatible
- Memoria insuficiente
- Conflicto con otro plugin

**Soluciones**:
1. Verifica la versión de PHP en tu hosting
2. Aumenta la memoria PHP a 512MB
3. Desactiva otros plugins temporalmente

### Error: "API Key inválida"

**Verificaciones**:
1. Copia y pega la API Key nuevamente
2. Verifica que no haya espacios extra
3. Confirma que la clave esté activa en Google AI Studio

### Error: "No se generan preguntas"

**Pasos de Diagnóstico**:
1. Verifica la conexión a internet
2. Revisa los logs de error de WordPress
3. Confirma que la API Key tenga cuota disponible
4. Prueba con una materia diferente

### Error: "reCAPTCHA no funciona"

**Verificaciones**:
1. Confirma que las claves sean correctas
2. Verifica que el dominio esté registrado
3. Asegúrate de que el sitio use HTTPS

## 📞 Soporte Técnico

### Antes de Contactar

1. **Revisa los Logs**:
   - Ve a **Herramientas → Salud del sitio**
   - Revisa los errores reportados

2. **Prueba Básica**:
   - Desactiva todos los plugins excepto WP Self Assessment
   - Cambia a un tema por defecto
   - Prueba nuevamente

3. **Información Necesaria**:
   - Versión de WordPress
   - Versión de PHP
   - Lista de plugins activos
   - Mensaje de error específico

### Contacto

- **Email**: soporte@guiaentuviaje.com
- **Documentación**: [Enlace a documentación completa]
- **Foro de Soporte**: [Enlace al foro]

## 🔄 Actualizaciones

### Actualización Automática

El plugin se actualiza automáticamente cuando hay nuevas versiones disponibles.

### Actualización Manual

1. Descarga la nueva versión
2. Desactiva el plugin actual
3. Sube la nueva versión
4. Activa el plugin

### Backup Antes de Actualizar

1. **Base de Datos**:
   - Exporta las tablas del plugin
   - Guarda la configuración

2. **Archivos**:
   - Haz backup de la carpeta del plugin
   - Guarda configuraciones personalizadas

## 📊 Monitoreo y Mantenimiento

### Tareas Regulares

1. **Revisar Logs**:
   - Verifica errores semanalmente
   - Monitorea el uso de la API

2. **Limpieza de Datos**:
   - Elimina autoevaluaciones antiguas
   - Optimiza la base de datos

3. **Actualizaciones**:
   - Mantén WordPress actualizado
   - Actualiza el plugin cuando sea necesario

### Métricas Importantes

- Número de autoevaluaciones completadas
- Tiempo promedio de respuesta de la API
- Errores de conexión
- Uso de cuota de API

---

**¡Instalación Completada!** 🎉

Tu plugin WP Self Assessment está listo para usar. Comienza creando tu primera materia y probando el sistema de autoevaluación.
